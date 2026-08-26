<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Context\EntityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EntityScope
{
    /**
     * Resolves the entity eagerly (the sanctum guard is stateless, so this is
     * safe before route middleware) — route-level auth:sanctum would otherwise
     * run *after* this middleware and leave the context empty.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        $entityId = $request->header('X-Entity-Id')
            ?? ($user instanceof User ? $user->entity_id : null);

        app()->instance(EntityContext::class, new EntityContext($entityId));

        return $next($request);
    }
}
