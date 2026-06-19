<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCmsIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('admin.cms_enabled', false)) {
            return $next($request);
        }

        return response()->view('admin.auth.login');
    }
}
