<?php

namespace App\Contracts\Auth;

use App\Exceptions\OpenIdConnectException;
use App\Support\OpenIdIdentity;

/**
 * The client of the OpenID Connect provider of the instance (#1385, ADR 0045): the address to send the member to, and
 * the identity that the provider vouches for. Behind a contract so that tests can fake the provider.
 */
interface ReadsOpenIdConnectIdentities
{
    /**
     * The address of the authorization endpoint, with state, nonce and the PKCE challenge (S256)
     *
     * @throws OpenIdConnectException When the provider cannot be reached or its discovery document is not acceptable
     */
    public function authorizationUrl(string $state, string $nonce, string $codeChallenge): string;

    /**
     * Exchange the code for tokens and return the identity of the ID token, after every check: the signature, `iss`,
     * `aud`, `exp`, `iat` and the nonce
     *
     * @throws OpenIdConnectException When the exchange fails or the ID token is not acceptable
     */
    public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity;
}
