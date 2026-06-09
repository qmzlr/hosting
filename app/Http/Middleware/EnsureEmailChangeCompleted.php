<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('user_id');
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user?->must_change_email) {
            return $next($request);
        }

        if ($this->isAllowedRequest($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            abort(423, 'Сначала смените email.');
        }

        return redirect()->route('profile');
    }

    private function isAllowedRequest(Request $request): bool
    {
        if ($request->isMethod('post') && $request->path() === 'logout') {
            return true;
        }

        if ($request->isMethod('get') && $request->path() === 'profile') {
            return true;
        }

        if ($request->isMethod('post') && $request->path() === 'api/profile/email-code') {
            return true;
        }

        return $request->isMethod('patch') && $request->path() === 'api/profile';
    }
}
