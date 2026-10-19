<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YoycolProduct extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variants' => 'array', 'published' => 'boolean', 'price_cents' => 'integer', 'synced_at' => 'datetime'];
    }

    public function productKey(): string
    {
        return 'yoycol-'.$this->id;
    }

    public function purchasable(): bool
    {
        return config('yoycol.enabled') && $this->published && $this->price_cents > 0
            && filled($this->design_code) && count($this->variants ?? []) > 0
            && $this->synced_at?->gt(now()->subDay());
    }

    public function catalogProduct(): array
    {
        return [
            'key' => $this->productKey(), 'title' => $this->title, 'summary' => $this->description,
            'amount_cents' => $this->price_cents, 'currency' => 'USD', 'kind' => 'physical',
            'source_type' => 'yoycol_product', 'source_id' => (string) $this->id,
            'image_url' => $this->image_url, 'image_alt' => $this->title,
            'unlock_type' => null, 'event' => null, 'inventory_tracked' => false,
        ];
    }
}
