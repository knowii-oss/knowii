<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\LinksIdentities;
use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\OpenIdIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Connect an account of an OpenID Connect provider to a member (#1385, ADR 0045). The unique indexes are the last
 * word: a provider account belongs to one member, and a member has one account per provider.
 */
class LinkIdentity implements LinksIdentities
{
    final public function link(User $user, OpenIdIdentity $identity): UserIdentity
    {
        try {
            $linked = DB::transaction(function () use ($user, $identity): UserIdentity {
                $existing = UserIdentity::query()->where('issuer', $identity->issuer)->where('subject', $identity->subject)->first();
                if ($existing !== null) {
                    if ($existing->user_id !== $user->id) {
                        throw new OpenIdConnectException('subject_linked_elsewhere', 'This account is already connected to another member of this site.');
                    }

                    // Connected already: nothing to add
                    return $existing;
                }

                if (UserIdentity::query()->where('user_id', $user->id)->where('issuer', $identity->issuer)->exists()) {
                    throw new OpenIdConnectException('issuer_already_linked', 'Your account is already connected to another account at this provider. Disconnect it first, then try again.');
                }

                return UserIdentity::query()->create([
                    'user_id' => $user->id,
                    'issuer' => $identity->issuer,
                    'subject' => $identity->subject,
                    'linked_at' => now(),
                    'last_used_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests at once: the index refused the second one
            throw new OpenIdConnectException('link_conflict', 'This account is already connected. Please try again.');
        }

        Log::info('Connected an OpenID Connect account', ['user' => $user->cuid, 'issuer' => $identity->issuer, 'identity' => $linked->cuid]);

        return $linked;
    }
}
