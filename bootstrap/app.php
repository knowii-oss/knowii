<?php

use App\Exceptions\AlreadyAuthenticatedException;
use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\AddViteContentSecurityPolicyNonce;
use App\Http\Middleware\EnsureRequestIsJson;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTokenAbility;
use App\Traits\ApiResponses;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        // Health check endpoint: https://laravel.com/docs/11.x/releases#health
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecureHeadersMiddleware::class,
            AddViteContentSecurityPolicyNonce::class,
            // noindex for members and private instances (#104)
            AddRobotsHeader::class,
        ]);

        $middleware->api([
            // WARNING: Do NOT add EncryptCookies, AddQueuedCookiesToResponse, StartSession or AuthenticateSession here.
            // statefulApi() (below) already runs them for first-party (stateful) requests through Sanctum's
            // EnsureFrontendRequestsAreStateful middleware. Running them twice starts a second session from the
            // already-decrypted cookie, which replaces the user's session cookie and logs them out.
            HandleCors::class,

            // Reject non-JSON request bodies (the API only accepts JSON input)
            EnsureRequestIsJson::class,

            // WARNING: Enabling this causes 302 redirects for API calls
            // \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,

            SubstituteBindings::class,

            SecureHeadersMiddleware::class,

            // The API is never indexed (#104)
            AddRobotsHeader::class,
        ]);

        $middleware->statefulApi();

        // Token abilities on the API routes (ADR 0022, #689): `ability:a,b` passes with any of them
        $middleware->alias([
            'ability' => RequireTokenAbility::class,
        ]);

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
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            // Workaround to use the ApiResponses trait
            // Reference: https://stackoverflow.com/questions/42054265/can-i-call-a-static-function-from-a-trait-outside-of-a-class
            // WARNING: should not include the exception message because it could expose sensitive information
            return (new class
            {
                use ApiResponses;
            })::notFoundIssue();
        });

        // Convert AuthenticationException instances to Knowii's error representation
        // (otherwise unauthenticated JSON API requests fall through to the generic
        // Exception handler below and return a 500 instead of a 401).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::authenticationIssue($e->getMessage(), null, null);
        });

        // Convert ValidationException instances to Knowii's error representation
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            // The messages per field (the framework types them as a plain array)
            /** @var array<string, array<int, string>> $errors */
            $errors = $e->errors();

            return (new class
            {
                use ApiResponses;
            })::validationIssue($e->getMessage(), $errors, null);
        });

        // Convert AccessDeniedHttpException instances to Knowii's error representation
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::authorizationIssue($e->getMessage(), null, null);
        });

        // Convert QueryException instances to Knowii's error representation
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::technicalIssue();
        });

        $exceptions->render(function (RelationNotFoundException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::technicalIssue();
        });

        // Convert BusinessException instances
        $exceptions->render(function (BusinessException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::businessIssue($e->getMessage(), $e->getErrors(), $e->getMetadata());
        });

        // Convert TechnicalException instances to Internal Server Exception
        $exceptions->render(function (TechnicalException $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::technicalIssue($e->getMessage(), $e->getErrors(), $e->getMetadata());
        });

        // Keep the status code and headers of the other HTTP exceptions (e.g., 405 Method Not Allowed, 429 Too Many Requests)
        // instead of turning them into a 500 below
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            // Symfony types the headers as a plain array: keep the string names with string (or string list) values
            $headers = [];
            foreach ($e->getHeaders() as $name => $value) {
                if (! is_string($name)) {
                    continue;
                }
                if (is_string($value)) {
                    $headers[$name] = $value;
                } elseif (is_array($value)) {
                    $headers[$name] = implode(', ', array_filter($value, 'is_string'));
                }
            }

            return (new class
            {
                use ApiResponses;
            })::httpIssue($e->getStatusCode(), $headers);
        });

        // Convert Exception instances to Internal Server Exception
        $exceptions->render(function (Exception $e, Request $request) {
            if (! $request->expectsJson()) {
                // Default Laravel processing if not expecting a JSON response
                return null;
            }

            return (new class
            {
                use ApiResponses;
            })::technicalIssue();
        });

        // Handle responses
        $exceptions->respond(function (Response|RedirectResponse|JsonResponse $response, Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                if ($exception instanceof AlreadyAuthenticatedException && $response instanceof JsonResponse) {
                    Log::debug('AlreadyAuthenticatedException');
                    $response->setJson('{ "message": "You are already logged in" }');
                    $response->setStatusCode(200);
                }
            }

            if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
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
