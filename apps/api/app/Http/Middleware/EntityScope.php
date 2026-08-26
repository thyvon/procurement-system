<?php

namespace App\Http\Middleware;

use App\Support\Context\EntityContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EntityScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $entityId = $request->header('X-Entity-Id')
            ?? $request->user()?->entity_id;

        app()->instance(EntityContext::class, new EntityContext($entityId));

        return $next($request);
    }
}
