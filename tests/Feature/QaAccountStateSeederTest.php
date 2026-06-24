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
        $this->clearQaPassword();

        parent::tearDown();
    }

    public function test_qa_account_state_seeder_requires_explicit_password(): void
    {
        $this->clearQaPassword();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RENY_QA_PASSWORD');

        $this->seed(QaAccountStateSeeder::class);
    }

    public function test_qa_account_state_seeder_skips_production_without_opt_in_or_password(): void
    {
        $this->clearQaPassword();
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('db:seed', ['--class' => QaAccountStateSeeder::class, '--force' => true])
            ->expectsOutputToContain('Skipping QA account fixtures in production.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_qa_account_state_seeder_creates_all_verification_accounts(): void
    {
        $this->setQaPassword('local-secret-for-tests');

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

        $registered = User::where('email', 'qa+registered@renyrenteria.test')->firstOrFail();
        $paymentFailed = User::where('email', 'qa+payment-failed@renyrenteria.test')->firstOrFail();
        $refunded = User::where('email', 'qa+refunded@renyrenteria.test')->firstOrFail();

        $this->assertTrue(Hash::check('local-secret-for-tests', $registered->password));
        $this->assertSame('payment_failed', $paymentFailed->accessState()->value);
        $this->assertSame('refunded', $refunded->accessState()->value);
    }

    private function setQaPassword(string $password): void
    {
        putenv('RENY_QA_PASSWORD='.$password);
        $_ENV['RENY_QA_PASSWORD'] = $password;
        $_SERVER['RENY_QA_PASSWORD'] = $password;
    }

    private function clearQaPassword(): void
    {
        putenv('RENY_QA_PASSWORD');
        unset($_ENV['RENY_QA_PASSWORD'], $_SERVER['RENY_QA_PASSWORD']);
    }
}
