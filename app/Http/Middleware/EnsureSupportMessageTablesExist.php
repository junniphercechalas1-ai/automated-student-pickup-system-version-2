<?php

namespace App\Http\Middleware;

use App\Support\SupportMessageSchema;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportMessageTablesExist
{
    public function handle(Request $request, Closure $next): Response
    {
        SupportMessageSchema::ensure();

        return $next($request);
    }
}
