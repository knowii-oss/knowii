<?php

use App\Exceptions\AlreadyAuthenticatedException;
use App\Exceptions\BusinessException;
use App\Exceptions\NoAccessException;
use App\Exceptions\TechnicalException;
use App\Http\Controllers\Inertia\InviteLinkController;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\AddViteContentSecurityPolicyNonce;
use App\Http\Middleware\AnswerOnlyForOwnHostNames;
use App\Http\Middleware\AnswerRefusalsInJson;
use App\Http\Middleware\AuthenticateIfPresent;
use App\Http\Middleware\EndOutdatedSessions;
use App\Http\Middleware\EnsureRequestIsJson;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordLastVisit;
use App\Http\Middleware\RefuseUnconfiguredInstance;
use App\Http\Middleware\RequireTokenAbility;
use App\Support\InstanceValues;
use App\Support\LegalPages;
use App\Support\SecretAddresses;
use App\Traits\ApiResponses;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
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
        // In production, an instance without an operator or a contact address answers only a 503 (#1653, ADR 0055): before
        // every middleware of the application and of the framework that reads a session, a cookie or the database, so that
        // every address answers so, the health address too. Only a middleware that a package prepends later runs before it
        $middleware->prepend(RefuseUnconfiguredInstance::class);

        // The application answers only for its own host names (APP_URL, TRUSTED_HOSTS and the loopback names), with a 400
        // for another one: right after the middleware that trusts the proxies, so that it reads the host name that the
        // proxy gives, and before every middleware that reads a session, a cookie or the database
        $middleware->insertAfter(TrustProxies::class, AnswerOnlyForOwnHostNames::class);

        $middleware->web(append: [
            // A session of an account that was taken over since its sign-in ends here (ADR 0059), before anything reads
            // the account of the session (see the priority below)
            EndOutdatedSessions::class,
            HandleInertiaRequests::class,
            // The last visit of a member, one date at most once a day (#1476)
            RecordLastVisit::class,
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

            // A session of the first-party front-end whose account was taken over since its sign-in ends here, as in the
            // web group (ADR 0059). A request with a token has no session: nothing to check
            EndOutdatedSessions::class,

            // WARNING: Enabling this causes 302 redirects for API calls
            // \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,

            SubstituteBindings::class,

            SecureHeadersMiddleware::class,

            // The API is never indexed (#104)
            AddRobotsHeader::class,
        ]);

        $middleware->statefulApi();

        // The end of a session of an earlier version (ADR 0059) comes before the middleware of the framework that reads the
        // account of the session: the sign-in check of the routes, the limits per account, the check of the password and the
        // bindings of the route
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EndOutdatedSessions::class);

        // A file of the API that a browser opens with a plain link (#1515): its refusals are the JSON errors of the API,
        // also the end of an outdated session and the sign-in check, so the browser never saves a page or the sign-in page
        // as the file. Before EndOutdatedSessions, which comes before the sign-in check (above)
        $middleware->prependToPriorityList(EndOutdatedSessions::class, AnswerRefusalsInJson::class);

        // Token abilities on the API routes (ADR 0022, #689): `ability:a,b` passes with any of them
        $middleware->alias([
            'ability' => RequireTokenAbility::class,
            // The routes that visitors may read (#1387, ADR 0044)
            'auth.optional' => AuthenticateIfPresent::class,
            // A file that a browser opens with a plain link (#1515)
            'json-refusals' => AnswerRefusalsInJson::class,
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

        // A failed form keeps its input in the session for the next page, never the token of a link (ADR 0064): the reset
        // page of the password reads its token from its props, not from the old input
        $exceptions->dontFlash(['token']);

        // A visitor on a page that visitors may read, but who may not read this one (a members-only space, a chat space,
        // a page that does not exist): the sign-in page, then back to the address (#1387, ADR 0044). Not found gives the
        // same answer as refused, so that nobody learns which private spaces exist. An address that holds a secret is never
        // the intended address (ADR 0064): the sign-in page then comes without it
        $exceptions->render(function (AccessDeniedHttpException|NotFoundHttpException $e, Request $request) {
            if ($request->attributes->get(AuthenticateIfPresent::VISITOR_ATTRIBUTE) === true && ! $request->expectsJson() && ! $request->is('api/*')) {
                return SecretAddresses::holdsASecret($request) ? redirect()->route('login') : redirect()->guest(route('login'));
            }

            return null;
        });

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

        // An outdated session (ADR 0059) that opens an invite link: the intended address is the page of the link, never the
        // address with the token (#1391, ADR 0062)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->routeIs('invite-links.open') ? InviteLinkController::afterAnOutdatedSession($request, $e) : null;
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

            // A visitor who may not read this on a route that accepts visitors (#1387): signing in may help, so a 401
            if ($request->attributes->get(AuthenticateIfPresent::VISITOR_ATTRIBUTE) === true) {
                return (new class
                {
                    use ApiResponses;
                })::authenticationIssue('Unauthenticated.', null, null);
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

            // The page without access (#1561) is its own answer, a 403, in every environment
            if ($exception instanceof NoAccessException) {
                return $response;
            }

            if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                // The two values of the footer, also when the failure came before the shared props (maintenance mode, a
                // middleware that failed): they read only the configuration and the folder of the legal texts
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                    'instance' => static fn (): array => InstanceValues::pageProp(),
                    'legalPages' => static fn (): array => LegalPages::available(),
                ])->toResponse($request)->setStatusCode($response->getStatusCode());
            }

            if ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();
