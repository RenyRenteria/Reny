<?php

namespace App\Services;

use App\Enums\ContentType;
use App\Enums\EditorialAuditAction;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\ContentReleaseWindow;
use App\Models\EditorialAuditLog;
use App\Models\EditorialContent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditorialWorkflowService
{
    public function createDraft(User $actor, array $attributes): EditorialContent
    {
        return DB::transaction(function () use ($actor, $attributes): EditorialContent {
            $content = EditorialContent::create([
                ...$this->basePayload($attributes),
                'status' => EditorialStatus::Draft->value,
                'needs_approval' => ! $actor->canPublishContent(),
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ]);

            $this->syncReleaseWindows($content, $attributes['release_windows'] ?? []);
            $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            $this->recordAudit($content, $actor, EditorialAuditAction::Created, $attributes);

            if ($content->needs_approval) {
                $this->recordAudit($content, $actor, EditorialAuditAction::ApprovalRequested);
            }

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function updateDraft(User $actor, EditorialContent $content, array $attributes): EditorialContent
    {
        return DB::transaction(function () use ($actor, $content, $attributes): EditorialContent {
            $content->fill($this->basePayload($attributes, $content));
            $content->forceFill([
                'status' => EditorialStatus::Draft->value,
                'needs_approval' => ! $actor->canPublishContent(),
                'updated_by_id' => $actor->id,
            ])->save();

            if (array_key_exists('release_windows', $attributes)) {
                $this->syncReleaseWindows($content, $attributes['release_windows'] ?? []);
            }

            if (array_key_exists('media_assets', $attributes)) {
                $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            }

            $this->recordAudit($content, $actor, EditorialAuditAction::Updated, $attributes);

            if ($content->needs_approval) {
                $this->recordAudit($content, $actor, EditorialAuditAction::ApprovalRequested);
            }

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function publish(User $actor, EditorialContent $content, array $attributes = []): EditorialContent
    {
        return DB::transaction(function () use ($actor, $content, $attributes): EditorialContent {
            if ($attributes !== []) {
                $content->fill($this->basePayload($attributes, $content));
            }

            $wasWaitingForApproval = $content->needs_approval;

            $content->forceFill([
                'status' => EditorialStatus::Published->value,
                'needs_approval' => false,
                'approved_by_id' => $actor->id,
                'published_by_id' => $actor->id,
                'approved_at' => now(),
                'published_at' => now(),
                'updated_by_id' => $actor->id,
            ])->save();

            if (array_key_exists('release_windows', $attributes)) {
                $this->syncReleaseWindows($content, $attributes['release_windows'] ?? []);
            }

            if (array_key_exists('media_assets', $attributes)) {
                $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            }

            if ($wasWaitingForApproval) {
                $this->recordAudit($content, $actor, EditorialAuditAction::Approved);
            }

            $this->recordAudit($content, $actor, EditorialAuditAction::Published, $attributes);

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function publishNew(User $actor, array $attributes): EditorialContent
    {
        return DB::transaction(function () use ($actor, $attributes): EditorialContent {
            $content = EditorialContent::create([
                ...$this->basePayload($attributes),
                'status' => EditorialStatus::Published->value,
                'needs_approval' => false,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
                'approved_by_id' => $actor->id,
                'published_by_id' => $actor->id,
                'approved_at' => now(),
                'published_at' => now(),
            ]);

            $this->syncReleaseWindows($content, $attributes['release_windows'] ?? []);
            $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            $this->recordAudit($content, $actor, EditorialAuditAction::Created, $attributes);
            $this->recordAudit($content, $actor, EditorialAuditAction::Published, $attributes);

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function schedule(
        User $actor,
        EditorialContent $content,
        CarbonInterface|string $scheduledAt,
        array $releaseWindows = [],
        array $attributes = []
    ): EditorialContent {
        return DB::transaction(function () use ($actor, $content, $scheduledAt, $releaseWindows, $attributes): EditorialContent {
            $scheduledAt = $this->normalizeScheduledAt($scheduledAt);

            if ($attributes !== []) {
                $content->fill($this->basePayload($attributes, $content));
            }

            $content->forceFill([
                'status' => EditorialStatus::Scheduled->value,
                'needs_approval' => false,
                'scheduled_at' => $scheduledAt,
                'scheduled_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ])->save();

            if ($releaseWindows !== [] || array_key_exists('release_windows', $attributes)) {
                $this->syncReleaseWindows($content, $releaseWindows);
            }

            if (array_key_exists('media_assets', $attributes)) {
                $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            }

            $this->recordAudit($content, $actor, EditorialAuditAction::Scheduled, [
                'scheduled_at' => $scheduledAt instanceof CarbonInterface ? $scheduledAt->toISOString() : $scheduledAt,
            ]);

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function scheduleNew(User $actor, array $attributes, CarbonInterface|string $scheduledAt): EditorialContent
    {
        return DB::transaction(function () use ($actor, $attributes, $scheduledAt): EditorialContent {
            $scheduledAt = $this->normalizeScheduledAt($scheduledAt);

            $content = EditorialContent::create([
                ...$this->basePayload([...$attributes, 'scheduled_at' => $scheduledAt]),
                'status' => EditorialStatus::Scheduled->value,
                'needs_approval' => false,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
                'scheduled_by_id' => $actor->id,
            ]);

            $this->syncReleaseWindows($content, $attributes['release_windows'] ?? []);
            $this->syncMediaAssets($content, $attributes['media_assets'] ?? []);
            $this->recordAudit($content, $actor, EditorialAuditAction::Created, $attributes);
            $this->recordAudit($content, $actor, EditorialAuditAction::Scheduled, [
                'scheduled_at' => $scheduledAt->toISOString(),
            ]);

            return $content->fresh(['releaseWindows', 'mediaAssets', 'auditLogs']);
        });
    }

    public function archive(User $actor, EditorialContent $content): EditorialContent
    {
        return DB::transaction(function () use ($actor, $content): EditorialContent {
            $content->forceFill([
                'status' => EditorialStatus::Archived->value,
                'archived_by_id' => $actor->id,
                'archived_at' => now(),
                'updated_by_id' => $actor->id,
            ])->save();

            $this->recordAudit($content, $actor, EditorialAuditAction::Archived);

            return $content->fresh(['auditLogs']);
        });
    }

    private function basePayload(array $attributes, ?EditorialContent $existingContent = null): array
    {
        $type = $this->enumValue($attributes['type'] ?? $existingContent?->type ?? ContentType::Post->value);
        $title = $attributes['title'] ?? $existingContent?->title ?? 'Untitled content';
        $slug = $attributes['slug'] ?? $existingContent?->slug ?? $title;
        $scheduledAt = array_key_exists('scheduled_at', $attributes)
            ? $this->normalizeScheduledAt($attributes['scheduled_at'])
            : $existingContent?->scheduled_at;

        return [
            'type' => $type,
            'title' => $title,
            'slug' => $this->uniqueSlug($type, $slug, $existingContent),
            'summary' => $attributes['summary'] ?? $existingContent?->summary,
            'body' => $attributes['body'] ?? $existingContent?->body,
            'visibility' => $this->enumValue($attributes['visibility'] ?? $existingContent?->visibility ?? VisibilityAudience::Open->value),
            'purchase_key' => $attributes['purchase_key'] ?? $existingContent?->purchase_key,
            'scheduled_at' => $scheduledAt,
            'metadata' => $attributes['metadata'] ?? $existingContent?->metadata ?? [],
        ];
    }

    private function syncReleaseWindows(EditorialContent $content, array $releaseWindows): void
    {
        $content->releaseWindows()->delete();

        collect($releaseWindows)
            ->map(fn (array $window): array => [
                'audience' => $this->enumValue($window['audience']),
                'starts_at' => $this->normalizeScheduledAt($window['starts_at'] ?? null),
                'ends_at' => $this->normalizeScheduledAt($window['ends_at'] ?? null),
                'country_codes' => $window['country_codes'] ?? null,
            ])
            ->each(fn (array $window): ContentReleaseWindow => $content->releaseWindows()->create($window));
    }

    private function syncMediaAssets(EditorialContent $content, array $mediaAssets): void
    {
        $content->mediaAssets()->detach();

        collect($mediaAssets)
            ->mapWithKeys(fn (array $asset): array => [
                (int) $asset['id'] => [
                    'role' => $asset['role'] ?? 'primary',
                    'sort_order' => $asset['sort_order'] ?? 0,
                    'metadata' => isset($asset['metadata']) ? json_encode($asset['metadata']) : null,
                ],
            ])
            ->each(function (array $pivot, int $assetId) use ($content): void {
                $content->mediaAssets()->attach($assetId, $pivot);
            });
    }

    private function recordAudit(
        EditorialContent $content,
        User $actor,
        EditorialAuditAction $action,
        array $changes = []
    ): EditorialAuditLog {
        return $content->auditLogs()->create([
            'actor_id' => $actor->id,
            'action' => $action->value,
            'changes' => $changes,
            'snapshot' => $content->fresh()?->only([
                'id',
                'type',
                'title',
                'status',
                'visibility',
                'needs_approval',
                'scheduled_at',
                'published_at',
                'archived_at',
            ]),
        ]);
    }

    private function uniqueSlug(string $type, string $value, ?EditorialContent $existingContent = null): string
    {
        $baseSlug = Str::slug($value) ?: 'untitled-content';
        $slug = $baseSlug;
        $suffix = 2;

        while (EditorialContent::query()
            ->where('type', $type)
            ->where('slug', $slug)
            ->when($existingContent?->exists, fn ($query) => $query->whereKeyNot($existingContent->getKey()))
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }

    private function normalizeScheduledAt(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->setTimezone('UTC');
        }

        return CarbonImmutable::parse((string) $value, config('admin.publishing_timezone', 'America/Panama'))
            ->setTimezone('UTC');
    }
}
