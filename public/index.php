<?php

use App\Http\Middleware\LimitRequestBodySize;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// A request body is limited (ADR 0073): refused from its length before the framework reads or decodes it...
$refusal = LimitRequestBodySize::answerBeforeTheFramework($_SERVER, static fn () => fopen('php://input', 'rb'));
if ($refusal !== null) {
    http_response_code($refusal['status']);
    foreach ($refusal['headers'] as $name => $value) {
        header($name.': '.$value);
    }
    echo $refusal['body'];

    exit;
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
