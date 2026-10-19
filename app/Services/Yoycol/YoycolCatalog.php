<?php

namespace App\Services\Yoycol;

use App\Models\YoycolProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class YoycolCatalog
{
    public function __construct(private readonly YoycolClient $client) {}

    public function sync(): int
    {
        return Cache::lock('yoycol-catalog-sync', 1800)->block(1, function (): int {
            $ids = [];
            for ($page = 1; $page <= 1000; $page++) {
                $result = $this->client->request('GET', 'product_templates', ['page' => $page, 'size' => 50]);
                if (! is_array($result['records'] ?? null)) {
                    throw ValidationException::withMessages(['yoycol' => 'Invalid catalog response.']);
                }
                foreach ($result['records'] as $summary) {
                    $id = (string) $summary['id'];
                    $detail = $this->client->request('GET', 'product_templates/'.rawurlencode($id));
                    if (empty($detail['designCode']) || ! is_array($detail['variants'] ?? null)) {
                        throw ValidationException::withMessages(['yoycol' => 'Incomplete product template.']);
                    }
                    $variants = collect($detail['variants'])->filter(fn (array $v): bool => filled($v['skuCode'] ?? null) && ($v['currency'] ?? null) === 'USD' && is_numeric($v['price'] ?? null)
                    )->map(fn (array $v): array => [
                        'id' => (string) $v['variantId'], 'sku' => $v['skuCode'],
                        // Shoe sizes arrive as attributes, with size=null.
                        'label' => collect($v['attributes'] ?? [])->pluck('valueText')->filter()->implode(' / ') ?: $v['skuCode'],
                        'cost_cents' => (int) round($v['price'] * 100),
                        'image_url' => $this->image(data_get($v, 'mockupImage.0.url')),
                    ])->values()->all();

                    $product = YoycolProduct::firstOrNew(['template_id' => $id]);
                    if (! $product->exists) {
                        $product->title = $summary['productName'] ?? $summary['designName'] ?? 'New merch';
                        $product->description = 'Made to order. Printed with an original Reny Rentería design.';
                    }
                    $product->fill([
                        'design_code' => $detail['designCode'], 'variants' => $variants,
                        'image_url' => $this->image($summary['previewImage'] ?? null), 'synced_at' => now(),
                    ])->save();
                    $ids[] = $id;
                }
                // Pagination metadata labels are ambiguous in the provider schema.
                // Continue to the first short page; never interpret total as a record count.
                if (count($result['records']) < 50) {
                    YoycolProduct::whereNotIn('template_id', $ids)->update(['published' => false, 'variants' => '[]']);

                    return count($ids);
                }
            }
            throw ValidationException::withMessages(['yoycol' => 'Catalog pagination limit reached.']);
        });
    }

    private function image(?string $url): ?string
    {
        return filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}
