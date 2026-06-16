<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:provision
    {email : Email address for the admin account}
    {--name= : Display name for a new account}
    {--role=admin : Admin role: admin, artist_admin, or editor}
    {--generate-password : Generate a strong password instead of prompting}
    {--rotate-password : Replace the password for an existing account}', function (): int {
    $email = Str::lower(trim((string) $this->argument('email')));
    $role = (string) $this->option('role');
    $nameOption = trim((string) $this->option('name'));
    $user = User::query()->where('email', $email)->first();
    $name = $nameOption !== '' ? $nameOption : ($user?->name ?: Str::before($email, '@'));

    $validator = Validator::make([
        'email' => $email,
        'name' => $name,
        'role' => $role,
    ], [
        'email' => ['required', 'email:rfc', 'max:255'],
        'name' => ['required', 'string', 'max:255'],
        'role' => ['required', 'in:'.implode(',', User::ADMIN_ROLES)],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $needsPassword = ! $user || (bool) $this->option('rotate-password');
    $password = null;
    $generatedPassword = null;

    if ($needsPassword) {
        $generatedPassword = $this->option('generate-password') ? Str::password(32) : null;

        if ($generatedPassword) {
            $password = $generatedPassword;
        } else {
            $password = $this->secret('Admin password');
            $confirmation = $this->secret('Confirm admin password');

            if ($password !== $confirmation) {
                $this->error('Admin password confirmation does not match.');

                return 1;
            }
        }

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Admin password must be at least 12 characters.');

            return 1;
        }
    }

    $attributes = [
        'name' => $name,
        'email' => $email,
        'email_verified_at' => $user?->email_verified_at ?? now(),
        'role' => $role,
    ];

    if ($needsPassword) {
        $attributes['password'] = $password;
    }

    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill($attributes)->save();

    $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' admin account: '.$user->email);
    $this->line('Role: '.$user->role);

    if ($generatedPassword) {
        $this->warn('Generated password shown once. Store it in the password manager and do not share it in Slack.');
        $this->line($generatedPassword);
    } elseif (! $needsPassword) {
        $this->line('Password left unchanged.');
    }

    return 0;
})->purpose('Create or promote an admin CMS account without committing credentials');
