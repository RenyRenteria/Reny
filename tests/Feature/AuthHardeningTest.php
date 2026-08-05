<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_is_rate_limited_by_identifier_and_ip(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => 'limited@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => 'limited@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_successful_public_login_clears_failed_attempt_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'cleared@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('account.show'));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->post(route('login.store'), [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect();
    }

    public function test_admin_login_is_aggressively_rate_limited_and_failures_are_logged(): void
    {
        Log::spy();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'missing-admin@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('admin.login.store'), [
            'email' => 'missing-admin@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        Log::shouldHaveReceived('warning')
            ->times(3)
            ->with('Admin login failed.', Mockery::on(fn (array $context): bool => $context['reason'] === 'invalid_credentials'
                && $context['ip'] === '127.0.0.1'
                && strlen($context['email_hash']) === 64
            ));
    }

    public function test_checkout_endpoints_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson(route('checkout.local'), [])->assertUnprocessable();
        }

        $this->postJson(route('checkout.local'), [])->assertTooManyRequests();
    }

    public function test_password_recovery_sends_a_real_link_for_phone_identifier(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'recovery@example.com',
            'phone' => '50760000000',
        ]);

        $this->post(route('password.email'), [
            'identifier' => '+507 6000-0000',
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'If the account exists, reset instructions will be sent.');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => Password::tokenExists($user, $notification->token),
        );
    }

    public function test_user_can_complete_password_reset_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('value="'.$user->email.'"', false);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Your password has been reset. Sign in with your new password.');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_production_hardening_is_documented(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        foreach ([
            'APP_DEBUG=false',
            'APP_URL=https://renyrenteria.com',
            'SESSION_SECURE_COOKIE=true',
            'SESSION_ENCRYPT=true',
            'LOG_LEVEL=warning',
        ] as $setting) {
            $this->assertStringContainsString($setting, $example);
        }
    }
}
