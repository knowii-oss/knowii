<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A user. The account fields (email, verification, two-factor status) and the profile are only for the user
 * themselves (#1145); other members get the public summary.
 *
 * @mixin User
 */
class UserResource extends AbstractKnowiiJsonResource
{
    /**
     * The account fields are only present for the user themselves (when() leaves them out otherwise)
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSelf = $this->resource instanceof User && ($request->user()?->is($this->resource) ?? false);

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->when($isSelf, fn () => $this->email),
            'email_verified_at' => $this->when($isSelf, fn () => $this->email_verified_at),
            'two_factor_enabled' => $this->when($isSelf, fn () => $this->two_factor_confirmed_at !== null),
            'profile' => $this->when($isSelf && $this->relationLoaded('profile'), fn () => $this->profile->toResource()),
            'created_at' => $this->created_at,
        ];
    }
}
