<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccountSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->must_change_password) {
            return redirect()->route('account.password');
        }
        if (! $request->user()->email_verified) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
