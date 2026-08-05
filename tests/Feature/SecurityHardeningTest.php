<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_is_throttled_by_normalized_identifier_and_ip(): void
    {
        User::factory()->create([
            'email' => 'fan@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        foreach (['FAN@example.com', 'fan@example.com', ' FAN@EXAMPLE.COM ', 'fan@example.com', 'fan@example.com'] as $identifier) {
            $this->post(route('login.store'), [
                'identifier' => $identifier,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => 'fan@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_successful_public_login_clears_previous_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'fan@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => 'fan@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => 'fan@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('home'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => 'fan@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'identifier' => 'fan@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_admin_login_is_aggressively_throttled_and_logs_failures_without_raw_email(): void
    {
        Log::spy();

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
            'role' => User::ROLE_ADMIN,
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'ADMIN@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        Log::assertLogged('warning', function (string $message, array $context): bool {
            return $message === 'Admin login failed.'
                && $context['reason'] === 'invalid_credentials'
                && $context['email_hash'] === hash('sha256', 'admin@example.com')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'admin@example.com');
        });

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_successful_admin_login_clears_previous_attempts(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
            'role' => User::ROLE_ADMIN,
        ]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_guest_checkout_budget_cannot_be_bypassed_by_rotating_identifiers(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('checkout.paypal.orders'), [
                'identifier' => 'first@example.com',
            ])->assertUnprocessable();
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('checkout.local'), [
                'identifier' => 'second@example.com',
            ])->assertUnprocessable();
        }

        $this->postJson(route('checkout.paypal'), [
            'identifier' => 'third@example.com',
        ])->assertTooManyRequests();
    }

    public function test_paypal_cancel_with_real_payload_shares_the_guest_checkout_budget(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson(route('checkout.paypal.orders'), [
                'identifier' => 'cancel-budget@example.com',
            ])->assertUnprocessable();
        }

        $this->postJson(route('checkout.paypal.orders.cancel'), [
            'paypal_order_id' => 'ORDER-NOT-FOUND',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_checkout_has_a_separate_budget_from_guest_checkout(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson(route('checkout.local'), [])->assertUnprocessable();
        }

        $this->actingAs(User::factory()->create())
            ->postJson(route('checkout.local'), [])
            ->assertUnprocessable();
    }

    public function test_password_reset_link_is_sent_for_email_or_normalized_phone(): void
    {
        Notification::fake();

        $emailUser = User::factory()->create(['email' => 'email@example.com']);
        $phoneUser = User::factory()->create([
            'email' => 'phone-owner@example.com',
            'phone' => '15551234567',
        ]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['identifier' => 'EMAIL@EXAMPLE.COM'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If recovery is available for this account, reset instructions will be sent.');

        $this->from(route('password.request'))
            ->post(route('password.email'), ['identifier' => '+1 (555) 123-4567'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If recovery is available for this account, reset instructions will be sent.');

        Notification::assertSentTo($emailUser, ResetPassword::class);
        Notification::assertSentTo($phoneUser, ResetPassword::class);
    }

    public function test_password_reset_response_does_not_enumerate_unknown_or_phone_only_accounts(): void
    {
        Notification::fake();

        $phoneOnlyUser = User::factory()->create([
            'email' => 'phone-15557654321@renyrenteria.local',
            'phone' => '15557654321',
        ]);

        foreach (['unknown@example.com', '+1 (555) 765-4321'] as $identifier) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['identifier' => $identifier])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', 'If recovery is available for this account, reset instructions will be sent.');
        }

        Notification::assertNothingSentTo($phoneOnlyUser);
    }

    public function test_password_can_be_reset_with_a_valid_broker_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new password');

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-once@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'first-new-password',
            'password_confirmation' => 'first-new-password',
        ];

        $this->post(route('password.store'), $payload)->assertRedirect(route('login'));

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                ...$payload,
                'password' => 'reused-token-password',
                'password_confirmation' => 'reused-token-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('first-new-password', $user->fresh()->password));
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'expired-reset@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'expired-token-password',
                'password_confirmation' => 'expired-token-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_reset_link_requests_are_throttled(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'reset@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('password.email'), ['identifier' => 'reset@example.com'])
                ->assertRedirect();
        }

        $this->post(route('password.email'), ['identifier' => 'reset@example.com'])
            ->assertTooManyRequests();
    }
}
