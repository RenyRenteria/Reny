<?php

namespace Database\Seeders;

use App\Models\BillingProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QaAccountStateSeeder extends Seeder
{
    public const PASSWORD = 'RenyQA!2026';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $accounts = [
        [
            'key' => 'registered',
            'name' => 'QA Registered Fan',
            'email' => 'qa+registered@renyrenteria.test',
            'username' => 'qa_registered',
            'royal_status' => 'open',
            'royal_ends_at' => null,
            'billing_status' => 'inactive',
            'order_status' => null,
        ],
        [
            'key' => 'royal_active',
            'name' => 'QA Royal Active',
            'email' => 'qa+royal-active@renyrenteria.test',
            'username' => 'qa_royal_active',
            'royal_status' => 'royal_active',
            'royal_ends_at' => '+30 days',
            'billing_status' => 'active',
            'order_status' => 'completed',
        ],
        [
            'key' => 'royal_expired',
            'name' => 'QA Royal Expired',
            'email' => 'qa+royal-expired@renyrenteria.test',
            'username' => 'qa_royal_expired',
            'royal_status' => 'royal_expired',
            'royal_ends_at' => '-2 days',
            'billing_status' => 'inactive',
            'order_status' => 'completed',
        ],
        [
            'key' => 'payment_failed',
            'name' => 'QA Payment Failed',
            'email' => 'qa+payment-failed@renyrenteria.test',
            'username' => 'qa_payment_failed',
            'royal_status' => 'payment_failed',
            'royal_ends_at' => '+7 days',
            'billing_status' => 'past_due',
            'order_status' => 'failed',
        ],
        [
            'key' => 'refunded',
            'name' => 'QA Refunded Fan',
            'email' => 'qa+refunded@renyrenteria.test',
            'username' => 'qa_refunded',
            'royal_status' => 'refunded',
            'royal_ends_at' => '-1 day',
            'billing_status' => 'refunded',
            'order_status' => 'refunded',
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(env('RENY_ALLOW_QA_FIXTURES', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('Skipping QA account fixtures in production. Set RENY_ALLOW_QA_FIXTURES=true to opt in.');

            return;
        }

        foreach ($this->accounts as $account) {
            $user = User::updateOrCreate([
                'email' => $account['email'],
            ], [
                'name' => $account['name'],
                'username' => $account['username'],
                'country_code' => 'PA',
                'locale' => 'en',
                'timezone' => 'America/Panama',
                'preferred_currency' => 'USD',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_FAN,
                'royal_status' => $account['royal_status'],
                'royal_ends_at' => $this->date($account['royal_ends_at']),
            ]);

            BillingProfile::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'provider' => 'paypal',
                'provider_customer_id' => 'QA-PAYER-'.$account['key'],
                'provider_subscription_id' => 'QA-SUB-'.$account['key'],
                'status' => $account['billing_status'],
                'payment_method_summary' => 'QA PayPal',
                'current_period_ends_at' => $user->royal_ends_at,
                'failed_payment_at' => $account['key'] === 'payment_failed' ? now()->subDay() : null,
                'last_synced_at' => now(),
                'metadata' => [
                    'source' => 'qa_account_state_seeder',
                    'state' => $account['key'],
                ],
            ]);

            if ($account['order_status']) {
                Order::updateOrCreate([
                    'provider_order_id' => 'QA-ACCOUNT-STATE-'.$account['key'],
                ], [
                    'user_id' => $user->id,
                    'provider' => 'paypal',
                    'product_key' => 'royal',
                    'amount_cents' => 499,
                    'currency' => 'USD',
                    'status' => $account['order_status'],
                    'grants_royal_month' => true,
                    'royal_granted_until' => $user->royal_ends_at,
                    'refunded_at' => $account['key'] === 'refunded' ? now()->subDay() : null,
                ]);
            }
        }
    }

    private function date(?string $relative): mixed
    {
        return $relative ? now()->modify($relative) : null;
    }
}
