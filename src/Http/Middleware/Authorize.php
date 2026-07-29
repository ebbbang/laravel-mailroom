<?php

namespace Ebbbang\TestMail\Http\Middleware;

use Closure;
use Ebbbang\TestMail\TestMail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(TestMail::check($request), 403);

        return $next($request);
    }
}
