<?php

namespace App\Services;

use App\Enums\AccessState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CommunityMemberDirectory
{
    public const PLAN_ALL = 'all';

    public const PLAN_FREE = 'free';

    public const PLAN_ROYAL = 'royal';

    /**
     * @return array{search: string, plan: string}
     */
    public function filters(Request $request): array
    {
        $search = mb_substr(trim((string) $request->query('member_search')), 0, 120);
        $plan = (string) $request->query('member_plan', self::PLAN_ALL);

        if (! in_array($plan, [self::PLAN_ALL, self::PLAN_FREE, self::PLAN_ROYAL], true)) {
            $plan = self::PLAN_ALL;
        }

        return compact('search', 'plan');
    }

    public function query(string $search = '', string $plan = self::PLAN_ALL): Builder
    {
        $matchingCountryCodes = $this->matchingCountryCodes($search);

        $query = User::query()
            ->where('role', User::ROLE_FAN)
            ->when($search !== '', function (Builder $query) use ($matchingCountryCodes, $search): void {
                $query->where(function (Builder $query) use ($matchingCountryCodes, $search): void {
                    $query
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('country_code', 'like', "%{$search}%");

                    if ($matchingCountryCodes !== []) {
                        $query->orWhereIn('country_code', $matchingCountryCodes);
                    }
                });
            });

        if ($plan === self::PLAN_ROYAL) {
            $this->royalConstraint($query);
        } elseif ($plan === self::PLAN_FREE) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNotIn('royal_status', $this->royalStatuses())
                    ->orWhereNull('royal_ends_at')
                    ->orWhere('royal_ends_at', '<=', now());
            });
        }

        return $query;
    }

    public function planLabel(User $user): string
    {
        return $user->hasRoyalAccess() ? 'Royal Pass' : 'Free';
    }

    public function countryLabel(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return self::COUNTRIES[$code] ?? ($code !== '' ? $code : '—');
    }

    private function royalConstraint(Builder $query): void
    {
        $query
            ->whereIn('royal_status', $this->royalStatuses())
            ->whereNotNull('royal_ends_at')
            ->where('royal_ends_at', '>', now());
    }

    /**
     * @return array<int, string>
     */
    private function royalStatuses(): array
    {
        return [AccessState::RoyalActive->value, AccessState::RoyalGrace->value];
    }

    /**
     * @return array<int, string>
     */
    private function matchingCountryCodes(string $search): array
    {
        if ($search === '') {
            return [];
        }

        return array_keys(array_filter(
            self::COUNTRIES,
            fn (string $country): bool => mb_stripos($country, $search) !== false,
        ));
    }

    private const COUNTRIES = [
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'CR' => 'Costa Rica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'ES' => 'Spain',
        'MX' => 'Mexico',
        'PA' => 'Panama',
        'PE' => 'Peru',
        'PR' => 'Puerto Rico',
        'US' => 'United States',
        'VE' => 'Venezuela',
    ];
}
