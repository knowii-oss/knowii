<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use Illuminate\Http\Response;

test('the API rejects a non-JSON request body with an unsupported media type response', function () {
    // A form-encoded POST (no application/json content type) to an API endpoint.
    $response = $this->post(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => 'someone@example.com',
        'password' => 'password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::UnsupportedMediaType->value);
});

test('the API accepts a JSON request body', function () {
    // A JSON POST is not rejected by the middleware (it proceeds to normal
    // handling, which here is a validation issue rather than 415).
    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the API does not require JSON for bodyless GET requests', function () {
    $response = $this->get(ApiRoutes::path(ApiRoutes::PING), [
        'Accept' => 'application/json',
    ]);

    $response->assertOk();
});
