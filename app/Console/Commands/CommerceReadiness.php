<?php

namespace App\Console\Commands;

use App\Models\YoycolProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CommerceReadiness extends Command
{
    protected $signature = 'commerce:readiness {--sandbox : Require the PayPal sandbox API}';

    protected $description = 'Check commerce configuration without exposing secrets or contacting payment providers';

    public function handle(): int
    {
        $expected = $this->option('sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $since = false;
        try {
            $since = filled(config('commerce.notifications_since')) && Carbon::parse(config('commerce.notifications_since'))->lte(now());
        } catch (Throwable) {
            // Invalid activation dates must not be treated as ready.
        }
        $checks = [
            'PayPal API matches requested environment' => rtrim((string) config('services.paypal.base_url'), '/') === $expected,
            'PayPal client, secret and webhook ID configured' => filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret')) && filled(config('services.paypal.webhook_id')),
            'Yoycol processing enabled with keys' => config('yoycol.enabled') && filled(config('yoycol.access_key')) && filled(config('yoycol.secret_key')),
            'Yoycol sales enabled' => (bool) config('yoycol.sales_enabled'),
            'Shipping destinations configured' => count(config('yoycol.countries', [])) > 0,
            'Shipping and returns policy configured (HTTPS)' => filter_var(config('yoycol.policy_url'), FILTER_VALIDATE_URL) && str_starts_with((string) config('yoycol.policy_url'), 'https://'),
            'Operations recipient configured' => (bool) filter_var(config('commerce.operations_email'), FILTER_VALIDATE_EMAIL),
            'Transactional notifications enabled with activation date' => config('commerce.notifications_enabled') && $since,
            'Mail delivery transport configured' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'Notification migration applied' => Schema::hasTable('commerce_notices'),
            'Fresh, published Yoycol products available' => Schema::hasTable('yoycol_products') && YoycolProduct::where('published', true)->get()->contains(fn ($product) => $product->purchasable()),
        ];
        foreach ($checks as $label => $ready) {
            $this->line(($ready ? 'PASS ' : 'FAIL ').$label);
        }
        $this->warn('Configuration only: separately verify rotated keys, approved margins/policies, scheduler, email delivery, real sandbox checkout and authorized fulfillment.');

        return collect($checks)->every(fn ($ready) => (bool) $ready) ? self::SUCCESS : self::FAILURE;
    }
}
