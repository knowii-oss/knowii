<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Knowii;
use App\Models\Community;
use App\Models\User;
use App\Rules\FreeEmailAddress;
use App\Support\PlainText;
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
use LogicException;

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
     * The checks of the sign-up form (ADR 0065): the fields that do not depend on the address. The form asks for the name
     * and the address; the password and the terms are on the page of the link (validateFinish()). The address gets the
     * checks of its format and of its length only: whether an account has it is for the queued job, so the answer is the
     * same for every address. Then the honeypot and the bot check of Turnstile.
     *
     * @param  array<mixed>  $input
     * @return array{name: string, email: string}
     *
     * @throws ValidationException
     */
    final public function validateForm(array $input): array
    {
        // The name is one line of plain text (ADR 0069): cleaned before it is counted
        PlainText::clean($input, ['name']);

        // WARNING: Those rules must remain aligned with those in UpdateUserProfileInformation.php
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:'.Constants::MAX_LENGTH_USER_NAME],
            'email' => ['required', 'string', 'email', 'max:'.Constants::MAX_LENGTH_USER_EMAIL],
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

        /** @var array{name: string, email: string} $validated */
        $validated = $validator->safe()->only(['name', 'email']);

        return $validated;
    }

    /**
     * The checks of the page of the link of a sign-up (ADR 0065): the name, the password, its confirmation and the terms
     *
     * @param  array<mixed>  $input
     * @return array{name: string, password: string}
     *
     * @throws ValidationException
     */
    final public function validateFinish(array $input): array
    {
        PlainText::clean($input, ['name']);
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:'.Constants::MAX_LENGTH_USER_NAME],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ]);

        $validator->validate();

        /** @var array{name: string, password: string} $validated */
        $validated = $validator->safe()->only(['name', 'password']);

        return $validated;
    }

    /**
     * The method of the contract of Fortify, which its own controller of the sign-up calls with the fields of the form. It
     * makes no account: the sign-up form queues a job and answers the same for every address, and the account is made by
     * the link of its mail (createFromLink(), ADR 0065). So a controller of Fortify that answers the form again, after an
     * upgrade, fails instead of making an account for a typed address.
     *
     * @param  array<mixed>  $input
     *
     * @throws LogicException
     */
    final public function create(array $input): User
    {
        throw new LogicException('The account of a sign-up is made by the link of its mail (ADR 0065), never by the form');
    }

    /**
     * The account of a sign-up whose address a link proved (ADR 0065): the fields of the page of the link (validateFinish())
     * and the address of the pending sign-up, never an address of the request. The account is verified from its first
     * write. A unique violation goes to the caller (isAddressTaken() tells which value was taken).
     *
     * @param  array<mixed>  $input  The fields of the page of the link
     * @param  string  $provedEmail  The address of the pending sign-up, which the link proved
     *
     * @throws ValidationException
     * @throws UniqueConstraintViolationException
     */
    final public function createFromLink(array $input, string $provedEmail): User
    {
        $fields = $this->validateFinish($input);

        // The zone of the browser (#1476, ADR 0048): the member asks for it by sending it. A zone that PHP does not know, or
        // none, gives null: the sign-up never fails because of it
        $zone = $input['time_zone'] ?? null;
        $timeZone = is_string($zone) ? TimeZones::orNull($zone) : null;

        return $this->createAccount($fields['name'], $provedEmail, $fields['password'], true, $timeZone);
    }

    /**
     * Whether a unique violation of the write of an account is about its address (another sign-up, a provider sign-in or a
     * second link was first). The name of the index tells which value was taken. The framework reads the name from the text
     * of the error, in English: a server that writes its messages in another language gives no name, and the address is
     * then checked again. Any other value (the username, the cuid, the slug of the personal community) is not the address.
     */
    public static function isAddressTaken(UniqueConstraintViolationException $e, string $email): bool
    {
        return $e->index === null
            ? FreeEmailAddress::isTaken($email)
            : in_array($e->index, self::ADDRESS_INDEXES, true);
    }

    /**
     * The creation path of every new account: a free username derived from the name, the account, its profile and its
     * personal community, in one transaction. The link of a sign-up (ADR 0065) and the sign-in with an OpenID Connect
     * provider (#1385) both end here. The caller has validated the values.
     *
     * @param  string|null  $password  Null for an account without a password (the provider proved who the member is)
     * @param  bool  $emailVerified  Whether the address is verified from the start (the link or the provider proved it)
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
            // Not mass assignable. A new account whose address a link or the provider proved is verified from its first write
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
