<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\QaAccountStateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QaAccountStateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_account_state_seeder_creates_reproducible_accounts(): void
    {
        $this->seed(QaAccountStateSeeder::class);

        $states = [
            'qa.open@renyrenteria.test' => 'open',
            'qa.royal.active@renyrenteria.test' => 'royal_active',
            'qa.royal.expired@renyrenteria.test' => 'royal_expired',
            'qa.royal.refunded@renyrenteria.test' => 'refunded',
            'qa.royal.payment_failed@renyrenteria.test' => 'payment_failed',
        ];

        foreach ($states as $email => $state) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertSame($state, $user->accessState()->value);
            $this->assertTrue(Hash::check(QaAccountStateSeeder::PASSWORD, $user->password));
        }

        $this->assertDatabaseHas('billing_profiles', [
            'provider_subscription_id' => 'QA-SUB-ACTIVE',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('billing_profiles', [
            'provider_subscription_id' => 'QA-SUB-REFUNDED',
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('billing_profiles', [
            'provider_subscription_id' => 'QA-SUB-FAILED',
            'status' => 'past_due',
        ]);
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'QA-ROYAL-REFUNDED-ORDER',
            'status' => 'refunded',
        ]);
    }
}
