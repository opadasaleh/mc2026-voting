<?php

use App\Exceptions\ApiError;
use App\Http\Middleware\EnsureOnSite;
use App\Http\Middleware\EnsureVisitorToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['on-site' => EnsureOnSite::class, 'visitor' => EnsureVisitorToken::class]);
        // Both need the bound Event model; on routes with both, the token is checked before the Wi-Fi gate.
        $middleware->appendToPriorityList(SubstituteBindings::class, EnsureVisitorToken::class);
        $middleware->appendToPriorityList(EnsureVisitorToken::class, EnsureOnSite::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API errors use the contract's { "error": { code, message, details } } shape.
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? ApiError::validationFailed($e->errors())->render()
            : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? ApiError::tooManyRequests((int) ($e->getHeaders()['Retry-After'] ?? 60))->render()->withHeaders($e->getHeaders())
            : null);
    })->create();
