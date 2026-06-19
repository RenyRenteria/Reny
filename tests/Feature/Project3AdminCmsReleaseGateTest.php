<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialAuditAction;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Project3AdminCmsReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_publish_or_schedule_existing_draft_by_manipulating_payload(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $draft = EditorialContent::factory()->create([
            'status' => EditorialStatus::Draft->value,
            'needs_approval' => true,
            'created_by_id' => $editor->id,
        ]);

        $this->actingAsAdmin($editor);

        $this->postJson(route('admin.editorial.publish'), [
            'content_id' => $draft->id,
            'title' => 'Forced publish',
            'status' => EditorialStatus::Published->value,
        ])->assertForbidden();

        $this->postJson(route('admin.editorial.schedule'), [
            'content_id' => $draft->id,
            'title' => 'Forced schedule',
            'scheduled_at' => now()->addDay()->toISOString(),
            'release_windows' => [
                [
                    'audience' => VisibilityAudience::Open->value,
                    'starts_at' => now()->addDay()->toISOString(),
                ],
            ],
        ])->assertForbidden();

        $draft = $draft->fresh();

        $this->assertSame(EditorialStatus::Draft, $draft->status);
        $this->assertTrue($draft->needs_approval);
        $this->assertNull($draft->published_by_id);
        $this->assertNull($draft->scheduled_by_id);
    }

    public function test_editorial_audit_gate_covers_update_publish_schedule_and_archive_actions(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workflow = app(EditorialWorkflowService::class);

        $content = $workflow->createDraft($editor, [
            'type' => ContentType::Post->value,
            'title' => 'Audit gate draft',
            'body' => 'Initial body.',
        ]);

        $content = $workflow->updateDraft($editor, $content, [
            'title' => 'Audit gate updated draft',
            'body' => 'Updated body.',
        ]);

        $content = $workflow->publish($admin, $content);

        $scheduled = $workflow->scheduleNew($admin, [
            'type' => ContentType::Video->value,
            'title' => 'Scheduled audit gate',
            'visibility' => VisibilityAudience::Member->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=abc12345678',
            ],
            'release_windows' => [
                [
                    'audience' => VisibilityAudience::Member->value,
                    'starts_at' => now()->addDay(),
                ],
            ],
        ], now()->addDay());

        $workflow->archive($admin, $content);

        $this->assertAuditActions($content->fresh(), [
            EditorialAuditAction::Created,
            EditorialAuditAction::ApprovalRequested,
            EditorialAuditAction::Updated,
            EditorialAuditAction::Approved,
            EditorialAuditAction::Published,
            EditorialAuditAction::Archived,
        ]);

        $this->assertAuditActions($scheduled->fresh(), [
            EditorialAuditAction::Created,
            EditorialAuditAction::Scheduled,
        ]);
    }

    public function test_member_first_open_later_release_window_blocks_guest_until_open_date(): void
    {
        $releaseStart = now()->setDate(2026, 7, 1)->setTime(9, 0);
        $openStart = $releaseStart->copy()->addWeek();

        $this->travelTo($releaseStart);

        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Member first release',
            'body' => 'Members see this before public launch.',
            'visibility' => VisibilityAudience::Open->value,
        ]);

        $content->releaseWindows()->createMany([
            [
                'audience' => VisibilityAudience::Member->value,
                'starts_at' => $releaseStart,
            ],
            [
                'audience' => VisibilityAudience::Open->value,
                'starts_at' => $openStart,
            ],
        ]);

        $member = User::factory()->create();

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Member first release');

        $this->get(route('public.content.show', $content))
            ->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get('/community')
            ->assertOk()
            ->assertSee('Member first release');

        $this->actingAs($member)
            ->get(route('public.content.show', $content))
            ->assertOk()
            ->assertSee('Members see this before public launch.');

        $this->travelTo($openStart->copy()->addMinute());

        $this->get('/community')
            ->assertOk()
            ->assertSee('Member first release');

        $this->get(route('public.content.show', $content))
            ->assertOk()
            ->assertSee('Members see this before public launch.');
    }

    public function test_expired_release_window_removes_server_side_access(): void
    {
        $windowStart = now()->setDate(2026, 8, 1)->setTime(9, 0);
        $windowEnd = $windowStart->copy()->addDay();
        $member = User::factory()->create();

        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Expired member drop',
            'body' => 'Time-boxed member content.',
            'visibility' => VisibilityAudience::Member->value,
        ]);

        $content->releaseWindows()->create([
            'audience' => VisibilityAudience::Member->value,
            'starts_at' => $windowStart,
            'ends_at' => $windowEnd,
        ]);

        $this->travelTo($windowStart->copy()->addHour());

        $this->actingAs($member)
            ->get(route('public.content.show', $content))
            ->assertOk()
            ->assertSee('Time-boxed member content.');

        $this->travelTo($windowEnd->copy()->addSecond());

        $this->actingAs($member)
            ->get(route('public.content.show', $content))
            ->assertForbidden();

        $this->actingAs($member)
            ->get('/community')
            ->assertOk()
            ->assertDontSee('Expired member drop');
    }

    public function test_mux_upload_asset_created_webhook_moves_pending_asset_to_processing(): void
    {
        config(['services.mux.webhook_secret' => 'test-webhook-secret']);

        $asset = $this->muxAsset([
            'processing_status' => MediaProcessingStatus::Pending->value,
            'mux_upload_id' => 'mux-upload-asset-created',
        ]);

        $payload = [
            'id' => 'event-upload-asset-created',
            'type' => 'video.upload.asset_created',
            'data' => [
                'id' => 'mux-upload-asset-created',
                'asset_id' => 'mux-asset-created',
                'status' => 'asset_created',
            ],
        ];

        $this->postSignedMuxWebhook($payload)
            ->assertOk()
            ->assertJsonPath('media_asset_id', $asset->id);

        $asset = $asset->fresh();

        $this->assertSame(MediaProcessingStatus::Processing, $asset->processing_status);
        $this->assertSame('mux-asset-created', $asset->mux_asset_id);
        $this->assertSame('asset_created', $asset->mux_status);
        $this->assertSame('video.upload.asset_created', $asset->metadata['mux_last_event_type']);
    }

    public function test_mux_webhook_rejects_stale_signature_without_mutating_asset(): void
    {
        config([
            'services.mux.webhook_secret' => 'test-webhook-secret',
            'services.mux.webhook_tolerance_seconds' => 300,
        ]);

        $asset = $this->muxAsset([
            'mux_asset_id' => 'mux-stale-asset',
            'processing_status' => MediaProcessingStatus::Processing->value,
        ]);

        $payload = [
            'type' => 'video.asset.ready',
            'data' => [
                'id' => 'mux-stale-asset',
                'status' => 'ready',
                'playback_ids' => [
                    ['id' => 'playback-stale', 'policy' => 'public'],
                ],
            ],
        ];

        $body = json_encode($payload);
        $timestamp = (string) now()->subMinutes(10)->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'test-webhook-secret');

        $this->call('POST', route('mux.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MUX_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body)->assertUnauthorized();

        $asset = $asset->fresh();

        $this->assertSame(MediaProcessingStatus::Processing, $asset->processing_status);
        $this->assertNull($asset->mux_playback_id);
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    /**
     * @param  array<int, EditorialAuditAction>  $expected
     */
    private function assertAuditActions(EditorialContent $content, array $expected): void
    {
        $actions = $content->auditLogs()
            ->get()
            ->map(fn ($log): string => $log->action instanceof EditorialAuditAction
                ? $log->action->value
                : (string) $log->action)
            ->all();

        foreach ($expected as $action) {
            $this->assertContains($action->value, $actions);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function muxAsset(array $overrides = []): MediaAsset
    {
        $uploadId = $overrides['mux_upload_id'] ?? 'mux-upload-123';

        return MediaAsset::create([
            'type' => MediaAssetType::ShortVideo->value,
            'title' => 'Release gate video',
            'disk' => 'mux',
            'path' => 'mux://uploads/'.$uploadId,
            'original_filename' => 'release-gate-video.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => 1024,
            'processing_status' => MediaProcessingStatus::Processing->value,
            ...$overrides,
        ]);
    }

    private function postSignedMuxWebhook(array $payload)
    {
        $body = json_encode($payload);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'test-webhook-secret');

        return $this->call('POST', route('mux.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MUX_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body);
    }
}
