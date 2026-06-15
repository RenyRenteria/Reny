<?php

namespace App\Http\Controllers;

use App\Services\PointLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request, PointLedgerService $points): View
    {
        $user = $request->user()->load('billingProfile');

        return view('account.show', [
            'accessState' => $user->accessState()->value,
            'billingProfile' => $user->billingProfile,
            'initials' => $this->initials($user->name),
            'leaderboard' => $points->leaderboard(5),
            'pointBalance' => $points->balance($user),
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
}
