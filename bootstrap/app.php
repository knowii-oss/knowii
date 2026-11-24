<?php

use App\Exceptions\AlreadyAuthenticatedException;
use App\Exceptions\BusinessException;
use App\Exceptions\NoAccessException;
use App\Exceptions\TechnicalException;
use App\Http\Controllers\Inertia\InviteLinkController;
use App\Http\Controllers\Mail\MailStopController;
use App\Http\Middleware\AcceptOnlyTheSessionOnPages;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\AddViteContentSecurityPolicyNonce;
use App\Http\Middleware\AnswerOnlyForOwnHostNames;
use App\Http\Middleware\AnswerRefusalsInJson;
use App\Http\Middleware\AuthenticateIfPresent;
use App\Http\Middleware\EndOutdatedSessions;
use App\Http\Middleware\EnsureRequestIsJson;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LandOnlyOnOwnHostNames;
use App\Http\Middleware\LimitRequestBodySize;
use App\Http\Middleware\RecordLastVisit;
use App\Http\Middleware\RefuseUnconfiguredInstance;
use App\Http\Middleware\RequireTokenAbility;
use App\Support\BrowserHistory;
use App\Support\InstanceValues;
use App\Support\LegalPages;
use App\Support\SecretAddresses;
use App\Traits\ApiResponses;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
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
        // Health check endpoint: https://laravel.com/docs/11.x/releases#health
        health: '/up',
    )
    // The rules of the live channels. Not through `channels:` of withRouting(), which also registers the authorization
    // route of the framework (GET and POST, with the `web` group only): routes/web.php registers it, for POST only, with the
    // sign-in checks of the pages
    ->booted(static function (): void {
        require __DIR__.'/../routes/channels.php';
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend([
            // The security headers of config/secure-headers.php are on every answer of the web and of the API, also on an
            // answer that a middleware gives (the 503 below, a redirect to the sign-in page, a 404 of a route binding, a 419,
            // a 429) and on the error page: first in the global stack, so that every answer comes back through it. It reads
            // only the configuration
            SecureHeadersMiddleware::class,

            // In production, an instance without an operator or a contact address answers only a 503 (#1653, ADR 0055):
            // before every middleware of the application and of the framework that reads a session, a cookie or the
            // database, so that every address answers so, the health address too. Only the security headers (above) and a
            // middleware that a package prepends later run before it
            RefuseUnconfiguredInstance::class,

            // The nonce of the Vite scripts is one of the policy, made before anything can render a page (the error page of
            // a middleware too)
            AddViteContentSecurityPolicyNonce::class,

            // A request body is limited to 1 MB, except on the upload routes (ADR 0073): before the session and before
            // anything reads or decodes the input
            LimitRequestBodySize::class,
        ]);

        // The application answers only for its own host names (APP_URL, TRUSTED_HOSTS and the loopback names), with a 400
        // for another one: right after the middleware that trusts the proxies, so that it reads the host name that the
        // proxy gives, and before every middleware that reads a session, a cookie or the database
        $middleware->insertAfter(TrustProxies::class, AnswerOnlyForOwnHostNames::class);

        $middleware->web(append: [
            // After a sign-in, the application goes only to an address of its own host names (ADR 0068): the session keeps
            // no other intended address (see the priority below)
            LandOnlyOnOwnHostNames::class,
            // A session of an earlier version of its account ends here (ADR 0059, ADR 0066: after a takeover, a change of
            // the password or of the second factor), before anything reads the account of the session (see the priority
            // below)
            EndOutdatedSessions::class,
            // The pages accept the session only (ADR 0022): a request whose account comes from an API token is refused,
            // right after the sign-in check of the route (see the priority below)
            AcceptOnlyTheSessionOnPages::class,
            HandleInertiaRequests::class,
            // The last visit of a member, one date at most once a day (#1476)
            RecordLastVisit::class,
            AddLinkHeadersForPreloadedAssets::class,
            // noindex for members and private instances (#104)
            AddRobotsHeader::class,
        ]);

        $middleware->api([
            // WARNING: Do NOT add EncryptCookies, AddQueuedCookiesToResponse, StartSession or AuthenticateSession here.
            // statefulApi() (below) already runs them for first-party (stateful) requests through Sanctum's
            // EnsureFrontendRequestsAreStateful middleware. Running them twice starts a second session from the
            // already-decrypted cookie, which replaces the user's session cookie and logs them out.
            HandleCors::class,

            // Every refusal of the API is the JSON of ADR 0011, whatever the `Accept` header says (#1716): also the refusals
            // that a middleware chooses before any exception, as the check of the email address (`verified`). The priority
            // list (below) puts it before the end of an outdated session, the sign-in check and `verified`
            AnswerRefusalsInJson::class,

            // Reject non-JSON request bodies (the API only accepts JSON input)
            EnsureRequestIsJson::class,

            // A session of the first-party front-end of an earlier version of its account ends here, as in the web group
            // (ADR 0059, ADR 0066). A request with a token has no session: nothing to check
            EndOutdatedSessions::class,

            // WARNING: Enabling this causes 302 redirects for API calls
            // \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,

            SubstituteBindings::class,

            // The API is never indexed (#104)
            AddRobotsHeader::class,

            // Every route of the API has a limit of requests (ADR 0073). The priority list (below) runs it after the sign-in
            // and the token ability, as the limiters of the routes; a route with a limiter of its own keeps it too
            'throttle:api',
        ]);

        $middleware->statefulApi();

        // The check of the intended address (ADR 0068) comes right after the start of the session: before every middleware
        // that writes the address (the sign-in check, the check of the email address, the confirmation of the password) and
        // before every answer that reads it
        $middleware->appendToPriorityList(StartSession::class, LandOnlyOnOwnHostNames::class);

        // The end of a session of an earlier version (ADR 0059) comes before the middleware of the framework that reads the
        // account of the session: the sign-in check of the routes, the limits per account, the check of the password and the
        // bindings of the route
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EndOutdatedSessions::class);

        // A file of the API that a browser opens with a plain link (#1515): its refusals are the JSON errors of the API,
        // also the end of an outdated session and the sign-in check, so the browser never saves a page or the sign-in page
        // as the file. Before EndOutdatedSessions, which comes before the sign-in check (above)
        $middleware->prependToPriorityList(EndOutdatedSessions::class, AnswerRefusalsInJson::class);

        // The pages accept the session only (ADR 0022): the check comes right after the sign-in check of the route, which
        // finds the account, and before every other check of the route (the limits, the check of the password of the
        // session, the check of the email address, the bindings)
        $middleware->appendToPriorityList(AuthenticatesRequests::class, AcceptOnlyTheSessionOnPages::class);

        // The abilities of a token (ADR 0022) and the check of the email address (`verified`) come before the item of the
        // route is read: after the sign-in check and before the bindings of the route, so that a refused request gets the
        // same answer whatever the URL names. Both read only the account, its token and the mark of a visitor, never a
        // parameter of the route. The abilities also come before the limits of the route: a token without the ability
        // uses up no try of the account
        $middleware->prependToPriorityList(ThrottleRequests::class, RequireTokenAbility::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureEmailIsVerified::class);

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

        // Every error of an address of the API is the JSON of ADR 0011, whatever the `Accept` header says (#1716): a client
        // such as curl sends `*/*`. The render callbacks below and the response hook read `expectsJson()`, so the request of
        // the API is marked as one that wants JSON, as `json-refusals` does. A page request does not change
        $rendersJson = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();
        $exceptions->shouldRenderJsonWhen($rendersJson);
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! $request->expectsJson()) {
                $request->headers->set('Accept', 'application/json');
            }

            return null;
        });

        // A stop link of a notification email that is not valid (#1478): the stop page in its "not valid" state for the GET, an
        // empty body for a POST, never the general error page
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            return $request->routeIs('mail.stop.*') ? MailStopController::invalid($request) : null;
        });

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

            // Symfony types the headers as a plain array: keep every header with a string name, its scalar values as strings
            // (Retry-After and X-RateLimit-* are integers)
            $headers = [];
            foreach ($e->getHeaders() as $name => $value) {
                if (! is_string($name)) {
                    continue;
                }
                $values = [];
                foreach (is_array($value) ? $value : [$value] as $item) {
                    if (is_scalar($item)) {
                        $values[] = (string) $item;
                    }
                }
                if ($values !== []) {
                    $headers[$name] = implode(', ', $values);
                }
            }

            return (new class
            {
                use ApiResponses;
            })::httpIssue($e->getStatusCode(), $headers);
        });

        // An exception that carries its own answer (a middleware, a limit with its own response): that answer, unchanged,
        // never the 500 below
        $exceptions->render(function (HttpResponseException $e) {
            return $e->getResponse();
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
        $exceptions->respond(function (Response|RedirectResponse|JsonResponse $response, Throwable $exception, Request $request) use ($rendersJson) {
            if ($request->expectsJson()) {
                if ($exception instanceof AlreadyAuthenticatedException && $response instanceof JsonResponse) {
                    Log::debug('AlreadyAuthenticatedException');
                    $response->setJson('{ "message": "You are already logged in" }');
                    $response->setStatusCode(200);
                }
            }

            // The page without access (#1561) and the stop page of a notification email (#1478) are their own answer, in every
            // environment
            if ($exception instanceof NoAccessException || $request->routeIs('mail.stop.*')) {
                return $response;
            }

            // The page `Error` is for a page request and an Inertia visit only: a request that expects JSON and a request of
            // the API keep the JSON error of ADR 0011 in every environment (#1716)
            if (! $rendersJson($request) && ! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                // The two values of the footer, also when the failure came before the shared props (maintenance mode, a
                // middleware that failed): they read only the configuration and the folder of the legal texts. The same for
                // the encryption of the history of the browser, which a page asks for only where the browser can do it
                BrowserHistory::decideEncryption($request);

                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                    'instance' => static fn (): array => InstanceValues::pageProp(),
                    'legalPages' => static fn (): array => LegalPages::available(),
                ])->toResponse($request)->setStatusCode($response->getStatusCode());
            }

            // An expired form token: a redirect back for a page, the JSON 419 of ADR 0011 for JSON and for the API (#1716)
            if ($response->getStatusCode() === 419 && ! $rendersJson($request)) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();
