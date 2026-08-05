<?php

namespace App\Services\Commerce;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommercePublicationValidator
{
    /**
     * Apply safe publication defaults and reject cards that would produce a broken CTA.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function prepareAndValidate(array $payload, ?EditorialContent $content = null): array
    {
        $type = ContentType::tryFrom((string) ($payload['type'] ?? $content?->type?->value));

        if (! $type || ! $this->isCommerceType($type, $payload, $content)) {
            return $payload;
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $isRsvp = $type === ContentType::Event
            && in_array(data_get($metadata, 'ticketing_mode'), ['rsvp'], true);
        $actionType = (string) data_get($metadata, 'action_type', $isRsvp ? 'rsvp' : 'buy');

        $metadata = [
            ...$metadata,
            'currency' => strtoupper((string) data_get($metadata, 'currency', 'USD')),
            'is_active' => filter_var(data_get($metadata, 'is_active', true), FILTER_VALIDATE_BOOL),
            'checkout_enabled' => filter_var(data_get($metadata, 'checkout_enabled', true), FILTER_VALIDATE_BOOL),
            'action_type' => $actionType,
            'cta_label' => trim((string) data_get($metadata, 'cta_label', match ($actionType) {
                'rsvp' => 'RSVP',
                'link' => 'VIEW DETAILS',
                default => 'BUY NOW',
            })),
        ];
        $payload['metadata'] = $metadata;
        $errors = [];
        $purchaseKey = trim((string) ($payload['purchase_key'] ?? $content?->purchase_key));

        if (! in_array($actionType, ['buy', 'rsvp', 'link'], true)) {
            $errors['metadata.action_type'] = 'Choose buy, RSVP or external link.';
        }

        if ($metadata['cta_label'] === '') {
            $errors['metadata.cta_label'] = 'Published commerce content requires a CTA label.';
        }

        if ($actionType === 'link') {
            $url = trim((string) data_get($metadata, 'action_url', data_get($metadata, 'url', '')));

            if (! $this->isSafeActionUrl($url)) {
                $errors['metadata.action_url'] = 'External-link content requires a valid HTTP(S) URL or internal path.';
            }
        } else {
            if ($purchaseKey === '' || preg_match('/^[A-Za-z0-9._-]+$/', $purchaseKey) !== 1) {
                $errors['purchase_key'] = 'Published commerce content requires a unique checkout or RSVP key.';
            }

            if ($purchaseKey !== '') {
                $duplicate = EditorialContent::query()
                    ->where('purchase_key', $purchaseKey)
                    ->where('status', '!=', EditorialStatus::Archived->value)
                    ->when($content?->exists, fn ($query) => $query->whereKeyNot($content->getKey()))
                    ->exists();

                if ($duplicate) {
                    $errors['purchase_key'] = 'This checkout or RSVP key is already used by active content.';
                }
            }
        }

        $amountCents = $this->amountCents($metadata);

        if ($actionType === 'buy' && ($amountCents === null || $amountCents <= 0)) {
            $errors['metadata.price_cents'] = 'Buy actions require a price greater than zero.';
        }

        if ($actionType === 'rsvp') {
            if ($type !== ContentType::Event) {
                $errors['metadata.action_type'] = 'RSVP is only available for events.';
            }

            if (($amountCents ?? 0) !== 0) {
                $errors['metadata.price_cents'] = 'RSVP events must be free; use ticket mode for paid events.';
            }
        }

        if (preg_match('/^[A-Z]{3}$/', $metadata['currency']) !== 1) {
            $errors['metadata.currency'] = 'Currency must be a three-letter ISO code such as USD.';
        } elseif ($actionType === 'buy' && $metadata['currency'] !== 'USD') {
            $errors['metadata.currency'] = 'Checkout currently settles in USD; publish buy actions in USD.';
        }

        if (! $metadata['is_active'] || ($actionType === 'buy' && ! $metadata['checkout_enabled'])) {
            $errors['metadata.is_active'] = 'Content must be active and checkout-enabled before publication.';
        }

        $inventory = data_get($metadata, 'inventory');

        if (is_numeric($inventory) && (int) $inventory <= 0 && $actionType === 'buy') {
            $errors['metadata.inventory'] = 'Buy actions require inventory greater than zero or a blank unlimited inventory.';
        }

        $availableFrom = data_get($metadata, 'available_from') ?? data_get($metadata, 'availability_starts_at');
        $availableUntil = data_get($metadata, 'available_until') ?? data_get($metadata, 'availability_ends_at');
        $referenceTime = filled($payload['scheduled_at'] ?? null)
            ? $this->publicationDate((string) $payload['scheduled_at'])
            : now();

        try {
            if (filled($availableFrom) && filled($availableUntil)
                && $this->publicationDate((string) $availableUntil)->lte($this->publicationDate((string) $availableFrom))) {
                $errors['metadata.available_until'] = 'Availability must end after it starts.';
            }

            if (filled($availableFrom) && $this->publicationDate((string) $availableFrom)->gt($referenceTime)) {
                $errors['metadata.available_from'] = 'Content cannot publish before its availability window opens.';
            }

            if (filled($availableUntil) && $this->publicationDate((string) $availableUntil)->lte($referenceTime)) {
                $errors['metadata.available_until'] = 'Content cannot publish after its availability window closes.';
            }
        } catch (Throwable) {
            $errors['metadata.available_from'] = 'Availability dates must be valid.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function isCommerceType(ContentType $type, array $payload, ?EditorialContent $content): bool
    {
        return in_array($type, [ContentType::Product, ContentType::Event], true);
    }

    /** @param array<string, mixed> $metadata */
    private function amountCents(array $metadata): ?int
    {
        if (is_numeric(data_get($metadata, 'price_cents'))) {
            return (int) data_get($metadata, 'price_cents');
        }

        if (is_numeric(data_get($metadata, 'price'))) {
            return (int) round((float) data_get($metadata, 'price') * 100);
        }

        return null;
    }

    private function isSafeActionUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function publicationDate(string $value): Carbon
    {
        return Carbon::parse($value, config('admin.publishing_timezone', 'America/Panama'));
    }
}
