<?php

use App\Exceptions\AlreadyAuthenticatedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    // Health check endpoint: https://laravel.com/docs/11.x/releases#health
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
      \App\Http\Middleware\HandleInertiaRequests::class,
      \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);

    $middleware->api([
      // Included in statefulApi()
      \Illuminate\Cookie\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
      \Illuminate\Session\Middleware\StartSession::class,
      \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
      \Illuminate\Http\Middleware\HandleCors::class,

      // WARNING: Enabling this causes 302 redirects
      //\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,

      \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);

    $middleware->statefulApi();

    // Might need this later if we still get redirects with the API
//    $middleware->redirectTo(function (Request $request) {
//      Log::info('Handling redirect to');
//      if ($request->expectsJson()) {
//        Log::info('Client expects JSON');
//        throw new AlreadyAuthenticatedException();
//      } else {
//        Log::info('Client does NOT expect JSON');
//      }
//      //return '/';
//    });
//
//    $middleware->redirectGuestsTo(function (Request $request) {
//      Log::info('Handling redirect guests to');
//      if ($request->expectsJson()) {
//        Log::info('Client expects JSON');
//        throw new AlreadyAuthenticatedException();
//      } else {
//        Log::info('Client does NOT expect JSON');
//      }
//      //return '/';
//    });
//
//    $middleware->redirectUsersTo(function (Request $request) {
//      Log::info('Handling redirect users to');
//      if ($request->expectsJson()) {
//        Log::info('Client expects JSON');
//        throw new AlreadyAuthenticatedException();
//      } else {
//        Log::info('Client does NOT expect JSON');
//      }
//      //return '/';
//    });

  })
  // References
  // https://inertiajs.com/error-handling
  // https://laravel.com/docs/11.x/errors#handling-exceptions
  ->withExceptions(function (Exceptions $exceptions) {

    // Convert ValidationException to Knowii's error representation
    // FIXME extract conversion logic to static utility function
    $exceptions->render(function (ValidationException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }
      return response()->json([
        'message' => $e->getMessage(),
        'error' => "lol",
        'data' => $e->errors()
      ], Response::HTTP_BAD_REQUEST);
    });

    //  "message": "The email field is required.",
    //  "errors": {
    //    "email": [
    //      "The email field is required."
    //    ]
    // }



    $exceptions->respond(function (Response | RedirectResponse | JsonResponse $response, Throwable $exception, Request $request) {
      if($request->expectsJson()) {
        Log::debug("Client expects JSON");
        if($exception instanceof AlreadyAuthenticatedException && $response instanceof JsonResponse) {
          Log::debug('AlreadyAuthenticatedException');
          $response->setJson('{ "message": "You are already logged in" }');
          $response->setStatusCode(200);
        }
      }

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
