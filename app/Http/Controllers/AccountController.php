<?php

namespace App\Http\Controllers;

use App\Models\PointLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('billingProfile');
        $pointBalance = (int) $user->pointLedgerEntries()
            ->where('status', 'posted')
            ->sum('delta');

        return view('account.show', [
            'accessState' => $user->accessState()->value,
            'billingProfile' => $user->billingProfile,
            'initials' => $this->initials($user->name),
            'leaderboard' => $this->leaderboard(),
            'pointBalance' => $pointBalance,
            'recentOrders' => $user->orders()->latest()->take(4)->get(),
            'unlocks' => $user->unlocks()->available()->latest('unlocked_at')->take(4)->get(),
            'upcomingTickets' => $user->tickets()
                ->with('event')
                ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
                ->latest('purchased_at')
                ->get()
                ->filter(fn ($ticket) => $ticket->event?->starts_at?->isFuture())
                ->sortBy(fn ($ticket) => $ticket->event->starts_at)
                ->take(4)
                ->values(),
            'user' => $user,
        ]);
    }

    private function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::of($part)->substr(0, 1)->upper())
            ->take(2)
            ->implode('');
    }

    /**
     * @return Collection<int, PointLedgerEntry>
     */
    private function leaderboard(): Collection
    {
        return PointLedgerEntry::query()
            ->select('user_id')
            ->selectRaw('SUM(delta) as points')
            ->where('status', 'posted')
            ->with('user:id,name,username')
            ->groupBy('user_id')
            ->orderByDesc('points')
            ->take(5)
            ->get();
    }
}
