<?php

use App\ApiRoutes;
use Symfony\Component\HttpFoundation\Response;

/*
 * The use of a sign-in link is limited per IP address, whatever the fields of the request, and the sign-in (the web and the
 * API) has a limit per IP address beside its limit per address and IP address.
 */

test('the use of a sign-in link takes 5 requests a minute from one IP address, whatever the email field', function () {
    $token = str_repeat('x', 64);

    foreach (range(1, 5) as $i) {
        newBrowser();
        $this->post('/login/link/'.$token, ['email' => "a$i@example.com"])->assertRedirect(route('login'));
    }
    newBrowser();
    $this->post('/login/link/'.$token, ['email' => 'a6@example.com'])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    // Another IP address is not counted
    newBrowser();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/login/link/'.$token)->assertRedirect(route('login'));
});

test('the sign-in takes 30 requests a minute from one IP address, for any addresses', function (string $path) {
    foreach (range(1, 30) as $i) {
        newBrowser();
        expect($this->postJson($path, ['email' => "person$i@example.com", 'password' => 'Summer2026!'])->status())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
    }
    newBrowser();
    $this->postJson($path, ['email' => 'person31@example.com', 'password' => 'Summer2026!'])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    // Another IP address is not counted, and after the minute the first one signs in again
    newBrowser();
    expect($this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson($path, ['email' => 'person32@example.com', 'password' => 'Summer2026!'])->status())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
    $this->travel(61)->seconds();
    newBrowser();
    expect($this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->postJson($path, ['email' => 'person33@example.com', 'password' => 'Summer2026!'])->status())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
})->with([
    'the web sign-in' => ['/login'],
    'the API sign-in' => [ApiRoutes::path(ApiRoutes::AUTH_LOGIN)],
]);
