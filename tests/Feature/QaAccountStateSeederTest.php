<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\QaAccountStateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class QaAccountStateSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->setQaPassword(null);

        parent::tearDown();
    }

    public function test_qa_account_state_seeder_requires_an_explicit_password(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RENY_QA_PASSWORD');

        $this->seed(QaAccountStateSeeder::class);
    }

    public function test_qa_account_state_seeder_creates_all_verification_accounts(): void
    {
        $this->setQaPassword('temporary-qa-secret');

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

        $paymentFailedUser = User::where('email', 'qa+payment-failed@renyrenteria.test')->firstOrFail();
        $refundedUser = User::where('email', 'qa+refunded@renyrenteria.test')->firstOrFail();

        $this->assertTrue(Hash::check('temporary-qa-secret', $paymentFailedUser->password));
        $this->assertSame('payment_failed', $paymentFailedUser->accessState()->value);
        $this->assertSame('refunded', $refundedUser->accessState()->value);
    }

    private function setQaPassword(?string $password): void
    {
        if ($password === null) {
            putenv('RENY_QA_PASSWORD');
            unset($_ENV['RENY_QA_PASSWORD'], $_SERVER['RENY_QA_PASSWORD']);

            return;
        }

        putenv("RENY_QA_PASSWORD={$password}");
        $_ENV['RENY_QA_PASSWORD'] = $password;
        $_SERVER['RENY_QA_PASSWORD'] = $password;
    }
}
