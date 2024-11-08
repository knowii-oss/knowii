<?php

use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

use App\Exceptions\AlreadyAuthenticatedException;
use App\Traits\ApiResponses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    // Health check endpoint: https://laravel.com/docs/11.x/releases#health
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
      \App\Http\Middleware\HandleInertiaRequests::class,
      \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
      \Bepsvpt\SecureHeaders\SecureHeadersMiddleware::class,
    ]);

    $middleware->api([
      // Included in statefulApi()
      \Illuminate\Cookie\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
      \Illuminate\Session\Middleware\StartSession::class,
      \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
      \Illuminate\Http\Middleware\HandleCors::class,

      // WARNING: Enabling this causes 302 redirects for API calls
      //\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,

      \Illuminate\Routing\Middleware\SubstituteBindings::class,

      \Bepsvpt\SecureHeaders\SecureHeadersMiddleware::class,
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

    // Convert NotFoundHttpException instances to Knowii's error representation
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      // Workaround to use the ApiResponses trait
      // Reference: https://stackoverflow.com/questions/42054265/can-i-call-a-static-function-from-a-trait-outside-of-a-class
      // WARNING: should not include the exception message because it could expose sensitive information
      return (new class { use ApiResponses; })::notFoundIssue();
    });

    // Convert ValidationException instances to Knowii's error representation
    $exceptions->render(function (ValidationException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::validationIssue($e->getMessage(), $e->errors(), null);
    });

    // Convert AccessDeniedHttpException instances to Knowii's error representation
    $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::authorizationIssue($e->getMessage(), null, null);
    });

    // Convert QueryException instances to Knowii's error representation
    $exceptions->render(function (QueryException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::technicalIssue();
    });

    $exceptions->render(function (RelationNotFoundException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::technicalIssue();
    });

    // Convert BusinessException instances
    $exceptions->render(function (BusinessException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::businessIssue($e->getMessage(), $e->getErrors(), $e->getMetadata());
    });

    // Convert TechnicalException instances to Internal Server Exception
    $exceptions->render(function (TechnicalException $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::technicalIssue($e->getMessage(), $e->getErrors(), $e->getMetadata());
    });

    // Convert Exception instances to Internal Server Exception
    $exceptions->render(function (Exception $e, Request $request) {
      if (!$request->expectsJson()) {
        // Default Laravel processing if not expecting a JSON response
        return null;
      }

      return (new class { use ApiResponses; })::technicalIssue();
    });

    // Handle responses
    $exceptions->respond(function (Response | RedirectResponse | JsonResponse $response, Throwable $exception, Request $request) {
      if($request->expectsJson()) {
        if($exception instanceof AlreadyAuthenticatedException && $response instanceof JsonResponse) {
          Log::debug('AlreadyAuthenticatedException');
          $response->setJson('{ "message": "You are already logged in" }');
          $response->setStatusCode(200);
        }
      }

      if (!app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
        return Inertia::render('Error', ['status' => $response->getStatusCode()])->toResponse($request)->setStatusCode($response->getStatusCode());
      }

      if ($response->getStatusCode() === 419) {
        return back()->with([
          'message' => 'The page expired, please try again.',
        ]);
      }

      return $response;
    });
  })->create();
