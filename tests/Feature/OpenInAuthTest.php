<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Tests\TestCase;

class OpenInAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_routes_render_open_in_screens(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Email or phone');

        $this->get('/register')
            ->assertOk()
            ->assertSee('Create account')
            ->assertSee('Public username')
            ->assertSee('Country')
            ->assertSee('brand-link-centered', false)
            ->assertDontSee('Open access');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Recover access');

        $this->get('/session-expired')
            ->assertOk()
            ->assertSee('Session expired');
    }

    public function test_registration_accepts_phone_and_starts_in_open_mode(): void
    {
        $response = $this->post('/register', [
            'name' => 'Reny Fan',
            'username' => '@RenyFan',
            'identifier' => '+1 (555) 101-2020',
            'country_code' => 'pa',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Reny Fan',
            'username' => 'renyfan',
            'phone' => '15551012020',
            'country_code' => 'PA',
            'royal_status' => 'open',
        ]);
    }

    public function test_registration_accepts_email_when_existing_users_have_no_phone(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'phone' => null,
        ]);

        $response = $this->post('/register', [
            'name' => 'New Fan',
            'username' => 'newfan',
            'identifier' => 'new@example.com',
            'country_code' => 'DO',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'New Fan',
            'username' => 'newfan',
            'email' => 'new@example.com',
            'phone' => null,
            'country_code' => 'DO',
            'royal_status' => 'open',
        ]);
    }

    public function test_registration_requires_unique_username(): void
    {
        User::factory()->create(['username' => 'takenfan']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Another Fan',
            'username' => 'takenfan',
            'identifier' => 'another@example.com',
            'country_code' => 'US',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('username');
    }

    public function test_forgot_password_sends_real_reset_link_for_email_identifier(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'recover@example.com',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', [
                'identifier' => 'recover@example.com',
            ])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'If this account has email recovery enabled, reset instructions will be sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_resolves_phone_identifier_when_account_has_email_recovery(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'phone-recover@example.com',
            'phone' => '15551012020',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', [
                'identifier' => '+1 (555) 101-2020',
            ])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'If this account has email recovery enabled, reset instructions will be sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_from_recovery_link(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-member@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = PasswordBroker::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Reset password');

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_user_can_sign_in_with_email_and_sidebar_uses_real_access_state(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Reny Member',
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'identifier' => 'member@example.com',
            'password' => 'password',
        ])->assertRedirect('/account');

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Reny Member')
            ->assertSee('ROYAL MEMBER')
            ->assertDontSee('Alex Carter')
            ->assertDontSee('VIP MEMBER');
    }

    public function test_successful_login_shows_one_success_box_on_account_screen(): void
    {
        User::factory()->create([
            'email' => 'success-member@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Login successful.');

        $response = $this->followingRedirects()->post('/login', [
            'identifier' => 'success-member@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertSee('Login successful.')
            ->assertSee('auth-success-box', false)
            ->assertDontSee('auth-status', false);

        $this->assertSame(1, substr_count($response->getContent(), 'auth-success-box'));
    }

    public function test_public_login_rate_limits_repeated_failures_by_identifier_and_ip(): void
    {
        User::factory()->create([
            'email' => 'limited-member@example.com',
            'password' => Hash::make('password'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')
                ->post('/login', [
                    'identifier' => 'limited-member@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login')
                ->assertSessionHasErrors('identifier');
        }

        $this->from('/login')
            ->post('/login', [
                'identifier' => 'limited-member@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_successful_public_login_clears_rate_limit_counter(): void
    {
        User::factory()->create([
            'email' => 'cleared-member@example.com',
            'password' => Hash::make('password'),
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')
                ->post('/login', [
                    'identifier' => 'cleared-member@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login');
        }

        $this->post('/login', [
            'identifier' => 'cleared-member@example.com',
            'password' => 'password',
        ])->assertRedirect('/account');

        $this->post('/logout')->assertRedirect('/');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')
                ->post('/login', [
                    'identifier' => 'cleared-member@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login');
        }

        $this->from('/login')
            ->post('/login', [
                'identifier' => 'cleared-member@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_expired_user_keeps_account_but_sees_reactivation_state(): void
    {
        $user = User::factory()->expiredRoyal()->create([
            'name' => 'Expired Member',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Expired Member')
            ->assertSee('Royal Expired')
            ->assertSee('Reactivate Royal Pass');
    }

    public function test_protected_route_preserves_intended_redirect_after_login(): void
    {
        $user = User::factory()->royal()->create([
            'email' => 'redirect-member@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->get('/royal/content/vip-mix')->assertRedirect('/login');

        $this->post('/login', [
            'identifier' => 'redirect-member@example.com',
            'password' => 'password',
        ])->assertRedirect('/royal/content/vip-mix');

        $this->actingAs($user)
            ->get('/royal/content/vip-mix')
            ->assertOk()
            ->assertSee('secure_stream_url:royal-only-vip-mix');
    }
}
