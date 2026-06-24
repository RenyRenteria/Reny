<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_create_default_or_qa_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'qa+registered@renyrenteria.test',
        ]);
    }
}
