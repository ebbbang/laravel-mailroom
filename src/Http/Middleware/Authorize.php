<?php

namespace Ebbbang\Mailroom\Http\Middleware;

use Closure;
use Ebbbang\Mailroom\Mailroom;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Mailroom::check($request), 403);

        return $next($request);
    }
}
