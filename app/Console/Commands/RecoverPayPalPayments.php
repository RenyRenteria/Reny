<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\PayPalPaymentRecovery;
use Illuminate\Console\Command;

class RecoverPayPalPayments extends Command
{
    protected $signature = 'paypal:reconcile';

    protected $description = 'Recover ambiguous customer payments with read-only PayPal lookups';

    public function handle(PayPalPaymentRecovery $payments): int
    {
        Order::where('provider', 'paypal')->whereIn('status', ['pending', 'payment_review'])
            ->where('metadata->checkout->capture_started', true)
            ->where('updated_at', '<', now()->subMinute())
            ->chunkById(50, function ($orders) use ($payments): void {
                foreach ($orders as $order) {
                    $payments->recover($order);
                }
            });
        $this->info('PayPal reconciliation finished. No new captures were requested.');

        return self::SUCCESS;
    }
}
