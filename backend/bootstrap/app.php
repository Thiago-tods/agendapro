<?php

use App\Modules\Companies\Http\Middleware\EnsureCompanyMember;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-based session authentication for the first-party SPA (Sanctum).
        $middleware->statefulApi();

        $middleware->alias([
            'company.member' => EnsureCompanyMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers in JSON, regardless of the Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent Portuguese messages for framework errors. They also hide internal details
        // such as model class names ("No query results for model ..."). Messages written by the
        // application itself (e.g. 409 conflicts) are kept as they are.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*', 'sanctum/*')) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }

            if (! $e instanceof HttpExceptionInterface) {
                return null;
            }

            $message = match ($e->getStatusCode()) {
                403 => 'Você não tem permissão para realizar esta ação.',
                404 => 'Recurso não encontrado.',
                405 => 'Método não permitido.',
                419 => 'Sua sessão expirou. Recarregue a página e tente novamente.',
                429 => 'Muitas requisições. Aguarde um momento e tente novamente.',
                default => null,
            };

            return $message === null ? null : response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
