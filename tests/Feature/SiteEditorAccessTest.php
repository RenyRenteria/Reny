<?php

namespace Tests\Feature;

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

    public function test_admin_can_open_music_site_editor_by_default(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertSee('Reny Site Editor')
            ->assertSee('Banner')
            ->assertSee('Guardar y publicar');
    }

    public function test_admin_site_editor_routes_stay_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'store']))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Reny Site Editor')
            ->assertDontSee('Preview publico')
            ->assertDontSee('CMS conectado');
    }

    public function test_site_editor_preview_stays_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.preview', ['page' => 'home']))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Guest')
            ->assertDontSee('Sign in');
    }

    public function test_unknown_site_editor_page_stays_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'not-real']))
            ->assertOk()
            ->assertSee('Enter');
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    private function actingAsParkedAdmin(User $user): void
    {
        config(['admin.cms_enabled' => false]);

        $this->actingAsAdmin($user);
    }
}
