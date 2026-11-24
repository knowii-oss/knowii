<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // The API allows requests with cookies from its own origin only (ADR 0068): a page of another origin calls the API
    // with a token in the `Authorization` header. The pages of the application have the same origin as the API
    'supports_credentials' => false,
];
