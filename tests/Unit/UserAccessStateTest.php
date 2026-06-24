<?php

namespace Tests\Unit;

use App\Enums\AccessState;
use App\Models\User;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAccessStateTest extends TestCase
{
    #[DataProvider('accessStateProvider')]
    public function test_user_access_state_resolves_account_status(
        ?string $royalStatus,
        ?CarbonImmutable $royalEndsAt,
        AccessState $expected,
    ): void {
        $user = new User([
            'royal_status' => $royalStatus,
            'royal_ends_at' => $royalEndsAt,
        ]);

        $this->assertSame($expected, $user->accessState());
    }

    public static function accessStateProvider(): array
    {
        return [
            'open account' => [null, null, AccessState::Open],
            'active royal access' => [AccessState::RoyalActive->value, CarbonImmutable::now()->addDay(), AccessState::RoyalActive],
            'grace access' => [AccessState::RoyalGrace->value, CarbonImmutable::now()->addDay(), AccessState::RoyalGrace],
            'expired date' => [AccessState::RoyalActive->value, CarbonImmutable::now()->subDay(), AccessState::RoyalExpired],
            'explicit expired status' => [AccessState::RoyalExpired->value, null, AccessState::RoyalExpired],
            'failed payment' => [AccessState::PaymentFailed->value, CarbonImmutable::now()->addDay(), AccessState::PaymentFailed],
            'legacy on hold status' => ['on_hold', CarbonImmutable::now()->addDay(), AccessState::PaymentFailed],
            'refunded payment' => [AccessState::Refunded->value, CarbonImmutable::now()->addDay(), AccessState::Refunded],
            'cancelled membership' => [AccessState::Cancelled->value, CarbonImmutable::now()->addDay(), AccessState::Cancelled],
        ];
    }
}
