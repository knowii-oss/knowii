<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The requests of the Bruno collection (documentation/api), as [method, path segments].
 *
 * @return list<array{method: string, segments: list<string>, file: string}>
 */
function brunoRequests(): array
{
    $requests = [];
    foreach (glob(dirname(__DIR__, 2).'/documentation/api/*.bru') ?: [] as $file) {
        $source = (string) file_get_contents($file);
        if (preg_match('/^(get|post|put|patch|delete) \{\s*\n\s*url: ([^\n]+)/m', $source, $match) !== 1) {
            continue;
        }
        $path = (string) preg_replace('/^\{\{BASE_URL\}\}/', '', trim($match[2]));
        $path = explode('?', $path)[0];
        $requests[] = [
            'method' => strtoupper($match[1]),
            'segments' => array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== '')),
            'file' => basename($file),
        ];
    }

    return $requests;
}

test('the Bruno collection has a request for every /api/v1 route', function () {
    $requests = brunoRequests();
    expect($requests)->not->toBeEmpty();

    $missing = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }
        $segments = explode('/', $route->uri());
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $found = false;
            foreach ($requests as $request) {
                if ($request['method'] !== $method || count($request['segments']) !== count($segments)) {
                    continue;
                }
                $matches = true;
                foreach ($segments as $index => $segment) {
                    // A route parameter matches any value (a cuid or a placeholder such as <post cuid>)
                    if (! str_starts_with($segment, '{') && $segment !== $request['segments'][$index]) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $method.' /'.$route->uri();
            }
        }
    }

    expect($missing)->toBe([]);
});

test('every Bruno request targets an existing /api/v1 route', function () {
    $routes = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $routes[] = ['method' => $method, 'segments' => explode('/', $route->uri())];
        }
    }

    $orphans = [];
    foreach (brunoRequests() as $request) {
        // "Not found" requests an unknown route on purpose
        if ($request['file'] === 'Not found.bru') {
            continue;
        }
        $found = false;
        foreach ($routes as $route) {
            if ($route['method'] !== $request['method'] || count($route['segments']) !== count($request['segments'])) {
                continue;
            }
            $matches = true;
            foreach ($route['segments'] as $index => $segment) {
                if (! str_starts_with($segment, '{') && $segment !== $request['segments'][$index]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            $orphans[] = $request['file'];
        }
    }

    expect($orphans)->toBe([]);
});
