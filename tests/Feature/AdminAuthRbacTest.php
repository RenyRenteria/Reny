<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_cannot_access_admin_shell(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_fan_cannot_access_admin_shell(): void
    {
        $fan = User::factory()->create(['role' => 'fan']);

        $this->actingAs($fan)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_shell_uses_private_path_and_not_predictable_admin_path(): void
    {
        $adminPath = '/7YDX5h38a6Q2sfrsW2pRv9CoU59RA5YWD2R7K3AuMA';

        $this->assertSame($adminPath, parse_url(route('admin.dashboard'), PHP_URL_PATH));
        $this->assertSame($adminPath.'/login', parse_url(route('admin.login'), PHP_URL_PATH));

        $this->get($adminPath)
            ->assertRedirect(route('admin.login'));

        $this->get('/admin')
            ->assertNotFound();
    }

    public function test_admin_can_sign_in_with_email_and_open_stats_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_authenticated_at');

        $this->assertAuthenticatedAs($admin);

        $this->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Homepage Views')
            ->assertSee('Paywall Views')
            ->assertSee('Royal Members')
            ->assertSee('Monthly Sales')
            ->assertDontSee('Dashboard editorial');
    }

    public function test_admin_login_returns_to_intended_music_site_editor_page(): void
    {
        $admin = User::factory()->create([
            'email' => 'music-admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $musicEditorUrl = route('admin.site-editor.show', ['page' => 'music']);

        $this->get($musicEditorUrl)
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.login.store'), [
            'email' => 'music-admin@example.com',
            'password' => 'password',
        ])
            ->assertRedirect($musicEditorUrl)
            ->assertSessionHas('admin_authenticated_at');

        $this->assertAuthenticatedAs($admin);

        $this->get($musicEditorUrl)
            ->assertOk()
            ->assertSee('Reny Site Editor')
            ->assertSee('Banner');
    }

    public function test_admin_login_rejects_non_admin_accounts(): void
    {
        User::factory()->create([
            'email' => 'fan@example.com',
            'password' => Hash::make('password'),
            'role' => 'fan',
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => 'fan@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_editor_can_save_draft_but_cannot_publish(): void
    {
        $editor = User::factory()->create([
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->signInToAdmin($editor);

        $this->postJson(route('admin.editorial.drafts.store'), [
            'title' => 'Draft from editor',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('needs_approval', true);

        $this->postJson(route('admin.editorial.publish'), [
            'title' => 'Draft from editor',
        ])->assertForbidden();
    }

    public function test_artist_admin_can_publish(): void
    {
        $artistAdmin = User::factory()->create([
            'email' => 'artist@example.com',
            'password' => Hash::make('password'),
            'role' => 'artist_admin',
        ]);

        $this->signInToAdmin($artistAdmin);

        $this->postJson(route('admin.editorial.publish'), [
            'title' => 'Approved release',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('needs_approval', false);
    }

    public function test_manipulated_publish_request_cannot_bypass_rbac(): void
    {
        config(['admin.cms_enabled' => true]);

        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)
            ->withSession(['admin_authenticated_at' => now()->timestamp])
            ->post(route('admin.editorial.publish'), [
                'title' => 'Forced publish',
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_admin_session_expires(): void
    {
        config(['admin.session_lifetime_minutes' => 1]);

        $admin = User::factory()->create([
            'email' => 'admin-expire@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->signInToAdmin($admin);

        $this->travel(2)->minutes();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Admin session expired. Sign in again to continue.');

        $this->assertGuest();
    }

    public function test_expired_session_returns_to_intended_music_editor_after_relogin(): void
    {
        config(['admin.session_lifetime_minutes' => 1]);

        $admin = User::factory()->create([
            'email' => 'music-expire@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $musicEditorUrl = route('admin.site-editor.show', ['page' => 'music']);

        $this->signInToAdmin($admin);

        $this->travel(2)->minutes();

        $this->get($musicEditorUrl)
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Admin session expired. Sign in again to continue.');

        $this->assertGuest();

        $this->post(route('admin.login.store'), [
            'email' => 'music-expire@example.com',
            'password' => 'password',
        ])->assertRedirect($musicEditorUrl);

        $this->assertAuthenticatedAs($admin);
    }

    private function signInToAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
