<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use Illuminate\Http\Response;

test('the API returns 405 with an Allow header when the HTTP method is not supported', function () {
    // The username availability endpoint only accepts POST (GET /communities lists communities since #828)
    $response = $this->getJson(ApiRoutes::path(ApiRoutes::USERS_IS_USERNAME_AVAILABLE));

    $response->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
    $response->assertHeader('Allow', 'POST');
    expect($response->json('type'))->toBe(KnowiiApiResponseType::MethodNotAllowed->value);
    expect($response->json('message'))->toBe('Method Not Allowed');
});

test('the API still returns 404 for an unknown route', function () {
    $response = $this->getJson('/api/v1/does-not-exist');

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});
