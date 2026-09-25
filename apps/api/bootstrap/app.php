<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\EntityScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [CorrelationId::class, EntityScope::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof HttpException => $e->getStatusCode(),
                default => 500,
            };

            $message = match (true) {
                $status >= 500 && ! config('app.debug') => 'Server Error',
                $e instanceof ValidationException => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                $e instanceof AuthenticationException => 'Unauthenticated.',
                $e instanceof AuthorizationException && blank($e->getMessage()) => 'Forbidden.',
                default => $e->getMessage() ?: 'Server Error',
            };

            return response()->json([
                'statusCode' => $status,
                'message' => $message,
                'error' => class_basename($e),
                'correlationId' => app()->bound('correlation_id') ? app('correlation_id') : null,
                ...($e instanceof ValidationException
                    ? ['errors' => $e->errors()]
                    : []),
            ], $status);
        });
    })->create();
