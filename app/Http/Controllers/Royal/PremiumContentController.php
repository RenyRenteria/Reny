<?php

namespace App\Http\Controllers\Royal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PremiumContentController extends Controller
{
    public function show(string $resource): View
    {
        return view('royal.content', [
            'resource' => $resource,
            'secureStreamToken' => 'secure_stream_url:royal-only-'.$resource,
        ]);
    }
}
