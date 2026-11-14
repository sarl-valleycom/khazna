<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevToolsEnabled
{
    /**
     * Make developer-only pages look nonexistent outside local and staging,
     * before authentication or authorization can reveal that they exist.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment(['local', 'staging']), 404);

        return $next($request);
    }
}
