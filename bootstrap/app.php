<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Request as RequestAlias;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '/api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            headers: RequestAlias::HEADER_X_FORWARDED_FOR |
            RequestAlias::HEADER_X_FORWARDED_HOST |
            RequestAlias::HEADER_X_FORWARDED_PORT |
            RequestAlias::HEADER_X_FORWARDED_PROTO |
            RequestAlias::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\LogApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            // HttpResponseException carries a ready-made response (e.g. rate limit callbacks)
            // — let it pass through untouched, never log as error
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            Log::error($e);
            if ($e instanceof NotFoundHttpException || $e instanceof RouteNotFoundException) {
                return response()->json([
                    'result' => [
                        'code' => 'CMSE404',
                        'message' => 'エラーが発生しました。もう一度お試しください。',
                    ],
                ], 404);
            }
            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'result' => [
                        'code'    => 'CMSE429',
                        'message' => 'ダウンロードに失敗しました。',
                    ],
                ], 429, ['Retry-After' => $e->getHeaders()['Retry-After'] ?? 60]);
            }
            return response()->json([
                'result' => [
                    'code' => 'CMSE500',
                    'message' => 'エラーが発生しました。もう一度お試しください。',
                ],
            ], 500);
        });
    })
    ->create();
