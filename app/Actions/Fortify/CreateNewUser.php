<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Knowii;
use App\Models\Community;
use App\Models\User;
use App\Rules\FreeEmailAddress;
use App\Support\TimeZones;
use App\Support\Turnstile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * The error of a sign-up that did not work, for a reason that the form does not name
     */
    public const string NOT_CREATED_MESSAGE = 'We could not create this account. Please try again.';

    /**
     * The unique indexes of the address of an account: the one of `email`, and the one of `lower(email)` (#1706, ADR 0058)
     *
     * @var list<string>
     */
    private const array ADDRESS_INDEXES = ['users_email_unique', 'users_email_lower_unique'];

    /**
     * Create a newly registered user.
     *
     * @param  array<mixed>  $input
     *
     * @throws ValidationException
     */
    final public function create(array $input): User
    {
        // WARNING: Those rules must remain aligned with those in UpdateUserProfileInformation.php
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:'.Constants::MAX_LENGTH_USER_NAME],
            // No other account has the address, without regard to the case (#1706, ADR 0058)
            'email' => ['required', 'email', 'max:'.Constants::MAX_LENGTH_USER_EMAIL, new FreeEmailAddress],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            // Bot checks (#737): the Turnstile token when the instance has Turnstile keys, and a honeypot
            'turnstile_token' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_TURNSTILE_TOKEN],
            Constants::SIGNUP_HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ]);

        $validator->validate();

        if (filled($input[Constants::SIGNUP_HONEYPOT_FIELD] ?? null)) {
            Log::notice('Sign-up refused: the honeypot field is filled');
            throw ValidationException::withMessages(['email' => self::NOT_CREATED_MESSAGE]);
        }

        // Instances without Turnstile keys (self-hosted, development) have no bot check on the sign-up
        if (Turnstile::isConfigured()) {
            $token = $input['turnstile_token'] ?? null;
            Turnstile::verify(is_string($token) ? $token : null, request()->ip(), 'turnstile_token');
        }

        // The zone of the browser (#1476, ADR 0048): the member asks for it by sending it. A zone that PHP does not know, or
        // none, gives null: the sign-up never fails because of it
        $zone = $input['time_zone'] ?? null;
        $timeZone = is_string($zone) ? TimeZones::orNull($zone) : null;

        /** @var array{name: string, email: string, password: string, terms: string} $input */
        $input = $validator->validated();

        try {
            return $this->createAccount($input['name'], $input['email'], $input['password'], false, $timeZone);
        } catch (UniqueConstraintViolationException $e) {
            // Another sign-up took the address between the check and the write: the error of the check, never an error page.
            // The name of the index tells which value was taken. Any other value (the username, the cuid, the slug of the
            // personal community) gets the error of a sign-up that did not work, never the error of a taken address
            if (in_array($e->index, self::ADDRESS_INDEXES, true)) {
                throw ValidationException::withMessages(FreeEmailAddress::takenMessages());
            }

            Log::warning('Sign-up refused: a unique value was taken at the write', ['index' => $e->index]);
            throw ValidationException::withMessages(['email' => self::NOT_CREATED_MESSAGE]);
        }
    }

    /**
     * The creation path of every new account: a free username derived from the name, the account, its profile and its
     * personal community, in one transaction. The sign-up form and the sign-in with an OpenID Connect provider (#1385)
     * both end here. The caller has validated the values.
     *
     * @param  string|null  $password  Null for an account without a password (the provider proved who the member is)
     * @param  bool  $emailVerified  Whether the address is verified from the start (the provider verified it)
     * @param  string|null  $timeZone  The zone that the member sent, already checked (TimeZones::orNull); null: none
     */
    final public function createAccount(string $name, string $email, ?string $password, bool $emailVerified = false, ?string $timeZone = null): User
    {
        // Derive username from the user's name
        $username = Knowii::generateRandomUsername($name);

        return DB::transaction(function () use ($username, $name, $email, $password, $emailVerified, $timeZone) {
            $user = new User([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password === null ? null : Hash::make($password),
            ]);
            // Not mass assignable. A new account that the provider proved is verified from its first write
            if ($emailVerified) {
                $user->forceFill(['email_verified_at' => now()]);
            }
            $user->save();

            return tap($user, function (User $user) use ($timeZone) {
                if ($timeZone !== null) {
                    $user->forceFill(['time_zone' => $timeZone])->save();
                }

                // Always a new profile: the profile of a deleted account is gone with it (#1261, ADR 0024)
                $user->profile()->create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                ]);

                $this->createCommunity($user);
            });
        });
    }

    /**
     * Create a personal community for the user.
     */
    final public function createCommunity(User $user): void
    {
        $communityName = $user->name;
        if ($user->name[-1] === 's') {
            $communityName .= "' Space";
        } else {
            $communityName .= "'s Space";
        }

        $user->ownedCommunities()->save(Community::forceCreate([
            'owner_id' => $user->id,
            'name' => $communityName,
            // The slug is generated automatically
            'description' => $user->name."'s Space",
            'visibility' => KnowiiCommunityVisibility::Personal,
        ]));
    }
}
