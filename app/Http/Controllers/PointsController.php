<?php

namespace App\Http\Controllers;

use App\Services\PointLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointsController extends Controller
{
    public function index(Request $request, PointLedgerService $points): View
    {
        return view('account.points', [
            'leaderboard' => $points->leaderboard(),
            'pointBalance' => $points->balance($request->user()),
            'user' => $request->user(),
        ]);
    }
}
