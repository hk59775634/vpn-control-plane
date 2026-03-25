<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', \App\Http\Middleware\NormalizeApiResponse::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'reseller_api_key' => \App\Http\Middleware\EnsureResellerApiKey::class,
            'reseller_portal' => \App\Http\Middleware\EnsureResellerPortalUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $shouldRespondJson = static function (Request $request): bool {
            return $request->expectsJson() || $request->is('api/*');
        };

        $apiError = static function (string $code, string $message, int $status = 422, array $data = []) {
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ], $status);
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            return $apiError(
                'VALIDATION_FAILED',
                $e->getMessage() ?: '请求参数校验失败',
                $e->status,
                ['errors' => $e->errors()]
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            return $apiError('UNAUTHENTICATED', '请先登录', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            return $apiError('FORBIDDEN', $e->getMessage() ?: '无权访问该资源', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            return $apiError('NOT_FOUND', '资源不存在', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            return $apiError('ROUTE_NOT_FOUND', '接口不存在', 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($shouldRespondJson, $apiError) {
            if (!$shouldRespondJson($request)) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() !== '' ? $e->getMessage() : '请求失败';
                $code = match ($status) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    422 => 'UNPROCESSABLE_ENTITY',
                    429 => 'TOO_MANY_REQUESTS',
                    default => 'HTTP_ERROR',
                };

                return $apiError($code, $message, $status);
            }

            return $apiError('INTERNAL_ERROR', '服务器内部错误', 500);
        });
    })->create();
