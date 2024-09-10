<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
      \App\Http\Middleware\HandleInertiaRequests::class,
      \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);

    //
  })
  // Health check endpoint: https://laravel.com/docs/11.x/releases#health
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
  )
  // Referencees
  // https://inertiajs.com/error-handling
  // https://laravel.com/docs/11.x/errors#handling-exceptions
  ->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response | RedirectResponse | JsonResponse $response, Throwable $exception, Request $request) {
      if (!app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
        return Inertia::render('Error', ['status' => $response->getStatusCode()])->toResponse($request)->setStatusCode($response->getStatusCode());
      } elseif ($response->getStatusCode() === 419) {
        return back()->with([
          'message' => 'The page expired, please try again.',
        ]);
      }

      return $response;
    });
  })->create();
