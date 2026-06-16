<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_admin_with_generated_password(): void
    {
        $this->artisan('admin:provision', [
            'email' => 'admin@example.com',
            '--name' => 'Admin User',
            '--generate-password' => true,
        ])
            ->expectsOutputToContain('Created admin account: admin@example.com')
            ->expectsOutputToContain('Role: admin')
            ->expectsOutputToContain('Generated password shown once.')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Admin User', $admin->name);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->canAccessAdmin());
        $this->assertNotNull($admin->email_verified_at);
        $this->assertFalse(Hash::needsRehash($admin->getRawOriginal('password')));
    }

    public function test_it_promotes_existing_user_without_rotating_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reny@example.com',
            'name' => 'Reny',
            'password' => Hash::make('current-password'),
            'role' => User::ROLE_FAN,
        ]);

        $this->artisan('admin:provision', [
            'email' => 'reny@example.com',
            '--role' => User::ROLE_ARTIST_ADMIN,
        ])
            ->expectsOutputToContain('Updated admin account: reny@example.com')
            ->expectsOutputToContain('Role: artist_admin')
            ->expectsOutputToContain('Password left unchanged.')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertSame(User::ROLE_ARTIST_ADMIN, $user->role);
        $this->assertSame('Reny', $user->name);
        $this->assertTrue(Hash::check('current-password', $user->password));
    }

    public function test_it_rejects_non_admin_roles(): void
    {
        $this->artisan('admin:provision', [
            'email' => 'admin@example.com',
            '--role' => User::ROLE_FAN,
            '--generate-password' => true,
        ])
            ->expectsOutputToContain('The selected role is invalid.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }
}
