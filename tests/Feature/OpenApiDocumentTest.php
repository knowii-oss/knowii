<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The OpenAPI document that Scramble generates (served on /docs/api).
 *
 * @return array{paths: array<string, array<string, array<string, mixed>>>}
 */
function openApiDocument(): array
{
    $path = tempnam(sys_get_temp_dir(), 'openapi');
    test()->artisan('scramble:export', ['--path' => $path])->assertSuccessful();
    /** @var array{paths: array<string, array<string, array<string, mixed>>>} $document */
    $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    unlink($path);

    return $document;
}

/**
 * The API routes of the application, as "METHOD /path" relative to /api (the server URL of the document).
 *
 * @return list<string>
 */
function applicationApiRoutes(): array
{
    $routes = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $uri = $route->uri();
        // Sanctum's CSRF cookie route is not part of the documented API
        if (! str_starts_with($uri, 'api/') || $uri === 'api/v1/csrf-cookie') {
            continue;
        }
        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }
            $routes[] = $method.' '.substr($uri, strlen('api'));
        }
    }

    return $routes;
}

test('the OpenAPI document describes every API route', function () {
    $document = openApiDocument();

    $documented = [];
    foreach ($document['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' '.$path;
        }
    }

    $routes = applicationApiRoutes();
    expect($routes)->not->toBeEmpty()
        ->and(array_values(array_diff($routes, $documented)))->toBe([]);
});

test('every documented operation documents its success response', function () {
    foreach (openApiDocument()['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            $responses = array_keys($operation['responses'] ?? []);
            $success = array_filter($responses, fn ($code) => str_starts_with((string) $code, '2'));
            expect($success)->not->toBeEmpty(strtoupper($method)." {$path} documents no success response");
        }
    }
});

test('every write operation documents its request body, except the older endpoints listed for #1194', function () {
    // Validation lives in the actions (ADR 0006), so these bodies need #[BodyParameter] on the controller (#1194).
    // Remove an entry when its body is declared: the test fails if the list is not current.
    $knownWithoutBody = [
        'POST /v1/communities',
        'POST /v1/communities/{community}/resource-collections',
        'POST /v1/communities/{community}/resource-collections/{communityResourceCollection}/text-articles',
        'POST /v1/users/is-username-available',
        'POST /v1/waitlist',
    ];

    $withoutBody = [];
    foreach (openApiDocument()['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (in_array($method, ['post', 'put', 'patch'], true) && empty($operation['requestBody'])) {
                $withoutBody[] = strtoupper($method).' '.$path;
            }
        }
    }

    expect($withoutBody)->toEqualCanonicalizing($knownWithoutBody);
});
