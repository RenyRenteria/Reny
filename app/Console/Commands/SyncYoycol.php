<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\YoycolFulfillment;
use App\Models\YoycolQuote;
use App\Services\Yoycol\YoycolCatalog;
use App\Services\Yoycol\YoycolFulfillmentService;
use App\Services\Yoycol\YoycolPaymentRecovery;
use Illuminate\Console\Command;

class SyncYoycol extends Command
{
    protected $signature = 'yoycol:sync {--catalog : Import templates and refresh variants}';

    protected $description = 'Submit paid merch orders and reconcile Yoycol payment and tracking';

    public function handle(YoycolCatalog $catalog, YoycolFulfillmentService $service, YoycolPaymentRecovery $payments): int
    {
        if (! config('yoycol.enabled')) {
            $this->line('Yoycol is disabled.');

            return self::SUCCESS;
        }
        if ($this->option('catalog')) {
            $this->info('Templates synchronized: '.$catalog->sync());

            return self::SUCCESS;
        }
        Order::whereHas('yoycolFulfillment')->whereIn('status', ['pending', 'payment_review'])
            ->where('metadata->checkout->capture_started', true)->where('updated_at', '<', now()->subMinute())
            ->chunkById(50, function ($orders) use ($payments): void {
                foreach ($orders as $order) {
                    $payments->recover($order);
                }
            });
        YoycolFulfillment::whereNotIn('state', ['cancelled', 'refund_review'])
            ->whereHas('order', fn ($query) => $query->whereIn('status', ['completed', 'refunded']))
            ->where(function ($query): void {
                $query->whereNull('synced_at')->orWhere('synced_at', '<', now()->subMinutes(15));
            })->chunkById(50, function ($rows) use ($service): void {
                foreach ($rows as $row) {
                    $service->process($row);
                }
            });
        YoycolQuote::where('expires_at', '<', now()->subDay())->delete();
        $this->info('Yoycol orders synchronized.');

        return self::SUCCESS;
    }
}
