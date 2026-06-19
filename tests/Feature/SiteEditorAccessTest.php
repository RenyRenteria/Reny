<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_editor_requires_private_admin_session(): void
    {
        $this->get(route('admin.site-editor.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_fan_cannot_access_site_editor(): void
    {
        $fan = User::factory()->create(['role' => User::ROLE_FAN]);

        $this->actingAs($fan)
            ->withSession(['admin_authenticated_at' => now()->timestamp])
            ->get(route('admin.site-editor.show', ['page' => 'home']))
            ->assertForbidden();
    }

    public function test_admin_can_open_visual_site_editor_preview(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'store']))
            ->assertOk()
            ->assertSee('Reny Site Editor')
            ->assertSee('Preview publico')
            ->assertSee('CMS conectado')
            ->assertSee('Falta modelo CMS')
            ->assertSee('/store')
            ->assertSee('Products and digital drops');
    }

    public function test_site_editor_lists_existing_cms_blocks_for_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        EditorialContent::factory()->published()->create([
            'type' => ContentType::Video->value,
            'title' => 'CMS Premiere Video',
            'summary' => 'Featured from the CMS.',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
                'access_tier' => VisibilityAudience::Open->value,
            ],
        ]);

        EditorialContent::factory()->create([
            'type' => ContentType::Video->value,
            'title' => 'Draft BTS Video',
            'status' => EditorialStatus::Draft->value,
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=Ue8orNrHw9s',
                'category' => 'behind-the-scenes',
                'access_tier' => VisibilityAudience::Open->value,
            ],
        ]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'videos']))
            ->assertOk()
            ->assertSee('CMS Premiere Video')
            ->assertSee('Draft BTS Video')
            ->assertSee('1 publicados')
            ->assertSee('1 borradores')
            ->assertSee('Agregar video');
    }

    public function test_unknown_site_editor_page_returns_not_found(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'not-real']))
            ->assertNotFound();
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
