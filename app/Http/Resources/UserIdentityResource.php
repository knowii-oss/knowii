<?php

namespace App\Http\Resources;

use App\Models\UserIdentity;
use App\Support\OpenIdConnect;
use Illuminate\Http\Request;

/**
 * An account of an OpenID Connect provider that the current member connected (#1385, ADR 0045): the issuer, the label of
 * the button when it is the provider of this instance, and the dates. Never the subject.
 *
 * @mixin UserIdentity
 */
class UserIdentityResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var UserIdentity $identity */
        $identity = $this->resource;

        return [
            'cuid' => $identity->cuid,
            'issuer' => $identity->issuer,
            'label' => OpenIdConnect::labelFor($identity->issuer),
            'linked_at' => $identity->linked_at,
            'last_used_at' => $identity->last_used_at,
        ];
    }
}
