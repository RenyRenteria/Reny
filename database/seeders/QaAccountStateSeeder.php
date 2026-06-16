<?php

namespace Database\Seeders;

use App\Models\BillingProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QaAccountStateSeeder extends Seeder
{
    public const PASSWORD = 'RenyQA2026!';

    /**
     * Seed QA accounts for account, auth redirect and entitlement gate checks.
     */
    public function run(): void
    {
        foreach ($this->accounts() as $account) {
            $user = User::updateOrCreate([
                'email' => $account['email'],
            ], [
                'name' => $account['name'],
                'username' => $account['username'],
                'phone' => null,
                'country_code' => 'PA',
                'locale' => 'en',
                'timezone' => 'America/Panama',
                'preferred_currency' => 'USD',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_FAN,
                'royal_status' => $account['royal_status'],
                'royal_ends_at' => $account['royal_ends_at'],
            ]);

            if ($account['billing_status']) {
                BillingProfile::updateOrCreate([
                    'user_id' => $user->id,
                ], [
                    'provider' => 'paypal',
                    'provider_customer_id' => $account['provider_customer_id'],
                    'provider_subscription_id' => $account['provider_subscription_id'],
                    'status' => $account['billing_status'],
                    'payment_method_summary' => 'PayPal QA',
                    'current_period_ends_at' => $account['royal_ends_at'],
                    'failed_payment_at' => $account['failed_payment_at'],
                    'last_synced_at' => now(),
                    'metadata' => [
                        'fixture' => 'qa_account_state',
                        'state' => $account['royal_status'],
                    ],
                ]);
            }

            if ($account['order_status']) {
                Order::updateOrCreate([
                    'provider_order_id' => $account['provider_order_id'],
                ], [
                    'user_id' => $user->id,
                    'provider' => 'paypal',
                    'product_key' => 'royal',
                    'amount_cents' => 499,
                    'currency' => 'USD',
                    'status' => $account['order_status'],
                    'grants_royal_month' => true,
                    'royal_granted_until' => $account['royal_ends_at'],
                    'refunded_at' => $account['order_status'] === 'refunded' ? now() : null,
                ]);
            }
        }

        $this->command?->info('Seeded QA account states. Password for all QA users: '.self::PASSWORD);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            [
                'email' => 'qa.open@renyrenteria.test',
                'name' => 'QA Open Fan',
                'username' => 'qa_open_fan',
                'royal_status' => 'open',
                'royal_ends_at' => null,
                'billing_status' => null,
                'provider_customer_id' => null,
                'provider_subscription_id' => null,
                'failed_payment_at' => null,
                'order_status' => null,
                'provider_order_id' => null,
            ],
            [
                'email' => 'qa.royal.active@renyrenteria.test',
                'name' => 'QA Royal Active',
                'username' => 'qa_royal_active',
                'royal_status' => 'royal_active',
                'royal_ends_at' => now()->addMonth(),
                'billing_status' => 'active',
                'provider_customer_id' => 'QA-PAYER-ACTIVE',
                'provider_subscription_id' => 'QA-SUB-ACTIVE',
                'failed_payment_at' => null,
                'order_status' => 'completed',
                'provider_order_id' => 'QA-ROYAL-ACTIVE-ORDER',
            ],
            [
                'email' => 'qa.royal.expired@renyrenteria.test',
                'name' => 'QA Royal Expired',
                'username' => 'qa_royal_expired',
                'royal_status' => 'royal_expired',
                'royal_ends_at' => now()->subDay(),
                'billing_status' => 'expired',
                'provider_customer_id' => 'QA-PAYER-EXPIRED',
                'provider_subscription_id' => 'QA-SUB-EXPIRED',
                'failed_payment_at' => null,
                'order_status' => 'completed',
                'provider_order_id' => 'QA-ROYAL-EXPIRED-ORDER',
            ],
            [
                'email' => 'qa.royal.refunded@renyrenteria.test',
                'name' => 'QA Royal Refunded',
                'username' => 'qa_royal_refunded',
                'royal_status' => 'refunded',
                'royal_ends_at' => now()->subHour(),
                'billing_status' => 'refunded',
                'provider_customer_id' => 'QA-PAYER-REFUNDED',
                'provider_subscription_id' => 'QA-SUB-REFUNDED',
                'failed_payment_at' => null,
                'order_status' => 'refunded',
                'provider_order_id' => 'QA-ROYAL-REFUNDED-ORDER',
            ],
            [
                'email' => 'qa.royal.payment_failed@renyrenteria.test',
                'name' => 'QA Payment Failed',
                'username' => 'qa_payment_failed',
                'royal_status' => 'payment_failed',
                'royal_ends_at' => now()->addWeek(),
                'billing_status' => 'past_due',
                'provider_customer_id' => 'QA-PAYER-FAILED',
                'provider_subscription_id' => 'QA-SUB-FAILED',
                'failed_payment_at' => now()->subHours(6),
                'order_status' => 'payment_failed',
                'provider_order_id' => 'QA-ROYAL-PAYMENT-FAILED-ORDER',
            ],
        ];
    }
}
