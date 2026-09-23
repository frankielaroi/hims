<?php

use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Azure App Service terminates TLS at its front end and forwards plain
         * HTTP to the container, so the X-Forwarded-* headers are the only
         * record of the original scheme and client IP (which the audit log
         * depends on). The container is not reachable except via that proxy.
         */
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            CheckAccountStatus::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'can_do' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * A refused action is as much a part of the regulatory record as a
         * permitted one — it is the evidence that the professional boundary
         * held. Every 403 is written to the audit log before the response is
         * rendered; returning null hands the exception back to Laravel so the
         * usual 403 page is still what the user sees.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            $isForbidden = $e instanceof AuthorizationException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403);

            if (! $isForbidden) {
                return null;
            }

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'authorization_denied',
                'description' => Str::limit($e->getMessage() ?: 'Action refused.', 255),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255),
                'metadata' => [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route' => $request->route()?->getName(),
                ],
            ]);

            return null;
        });
    })->create();
