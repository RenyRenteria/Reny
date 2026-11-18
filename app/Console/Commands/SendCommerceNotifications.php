<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\CommerceNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendCommerceNotifications extends Command
{
    protected $signature = 'commerce:notify';

    protected $description = 'Send order receipts, tracking updates and supplier payment/review reminders';

    public function handle(CommerceNotifications $notifications): int
    {
        if (! config('commerce.notifications_enabled') || ! config('commerce.notifications_since')) {
            $this->line('Commerce notifications are disabled.');

            return self::SUCCESS;
        }
        Order::with(['user', 'yoycolFulfillment'])->where('created_at', '>=', Carbon::parse(config('commerce.notifications_since')))->whereIn('status', ['completed', 'refunded', 'payment_review', 'pending'])
            ->chunkById(50, function ($orders) use ($notifications): void {
                foreach ($orders as $order) {
                    $notifications->send($order);
                }
            });

        return self::SUCCESS;
    }
}
