<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            'identifier' => '+1 (555) 101-2020',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Reny Fan',
            'phone' => '15551012020',
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
            'name' => '',
            'identifier' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Royal Member',
            'email' => 'new@example.com',
            'phone' => null,
            'royal_status' => 'open',
        ]);
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

    public function test_expired_user_keeps_account_but_sees_reactivation_state(): void
    {
        $user = User::factory()->expiredRoyal()->create([
            'name' => 'Expired Member',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Expired Member')
            ->assertSee('Open mode')
            ->assertSee('Get your Royal Pass');
    }
}
