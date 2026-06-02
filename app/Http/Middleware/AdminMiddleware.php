<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Consistent with RoleMiddleware: JSON/API requests receive a 403 response,
     * while browser requests are redirected to the dashboard with a user-friendly
     * error message instead of showing a raw 403 error page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'You do not have permission to perform this action.');
            }

            return redirect()->route('dashboard')->with(
                'error',
                'You do not have permission to access that page.'
            );
        }

        return $next($request);
    }
}
