<?php

namespace App\Services;

use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoyalPassService
{
    public function findOrCreateCustomer(string $identifier, bool $allowExisting = false): User
    {
        $user = $this->findCustomer($identifier);

        if ($user && $allowExisting) {
            return $user;
        }

        if ($user) {
            throw ValidationException::withMessages([
                'identifier' => 'Log in to checkout with this email or phone.',
            ]);
        }

        return $this->createCustomer($identifier);
    }

    public function findCustomer(string $identifier): ?User
    {
        ['email' => $email, 'phone' => $phone] = $this->contactFromIdentifier($identifier);

        return User::query()
            ->when($email, fn ($query) => $query->where('email', $email))
            ->when($phone, fn ($query) => $query->where('phone', $phone))
            ->first();
    }

    public function createCustomer(string $identifier): User
    {
        ['email' => $email, 'phone' => $phone] = $this->contactFromIdentifier($identifier);

        return User::create([
            'name' => 'Royal Member',
            'email' => $email ?: "phone-{$phone}@renyrenteria.local",
            'phone' => $phone ?: null,
            'password' => Hash::make(Str::password(24)),
            'role' => 'fan',
            'royal_status' => 'open',
        ]);
    }

    public function grantMonth(User $user, Order $order): User
    {
        $startsAt = $user->royal_ends_at?->isFuture() ? $user->royal_ends_at : now();
        $endsAt = $startsAt->copy()->addMonth();

        $user->forceFill([
            'royal_status' => 'royal_active',
            'royal_ends_at' => $endsAt,
        ])->save();

        $order->forceFill([
            'royal_granted_until' => $endsAt,
        ])->save();

        $this->log($user, 'membership_started', 'order', $order->provider_order_id, [
            'product_key' => $order->product_key,
            'royal_ends_at' => $endsAt->toIso8601String(),
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($user);

        return $user;
    }

    public function revokeGrant(Order $order): void
    {
        $order->forceFill([
            'status' => 'refunded',
            'refunded_at' => now(),
        ])->save();

        if (! $order->grants_royal_month || ! $order->user) {
            return;
        }

        $order->user->forceFill([
            'royal_status' => 'refunded',
            'royal_ends_at' => now(),
        ])->save();

        $this->log($order->user, 'membership_expired', 'order', $order->provider_order_id, [
            'reason' => 'refund',
            'product_key' => $order->product_key,
        ]);

        PublicCmsContentService::forgetCachedUserPayloads($order->user);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(?User $user, string $event, ?string $resourceType = null, ?string $resourceKey = null, array $metadata = []): void
    {
        AccessEvent::create([
            'user_id' => $user?->id,
            'event_name' => $event,
            'resource_type' => $resourceType,
            'resource_key' => $resourceKey,
            'metadata' => $metadata,
        ]);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @return array{email: string|null, phone: string|null}
     */
    private function contactFromIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : null;
        $phone = $email ? null : $this->normalizePhone($identifier);

        return [
            'email' => $email,
            'phone' => $phone,
        ];
    }
}
