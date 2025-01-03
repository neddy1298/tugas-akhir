<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(Auth::user()->role, ['admin', 'superadmin'])) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'You are not authorized to access this page',
            ]);
        }

        return $next($request);
    }
}
