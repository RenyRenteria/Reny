<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\QaAccountStateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaAccountStateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_account_state_seeder_creates_all_verification_accounts(): void
    {
        $this->seed(QaAccountStateSeeder::class);

        foreach ([
            'qa+registered@renyrenteria.test' => 'open',
            'qa+royal-active@renyrenteria.test' => 'royal_active',
            'qa+royal-expired@renyrenteria.test' => 'royal_expired',
            'qa+payment-failed@renyrenteria.test' => 'payment_failed',
            'qa+refunded@renyrenteria.test' => 'refunded',
        ] as $email => $status) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'royal_status' => $status,
            ]);
        }

        $this->assertSame('payment_failed', User::where('email', 'qa+payment-failed@renyrenteria.test')->firstOrFail()->accessState()->value);
        $this->assertSame('refunded', User::where('email', 'qa+refunded@renyrenteria.test')->firstOrFail()->accessState()->value);
    }
}
