<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController as JetstreamApiTokenController;
use Laravel\Jetstream\Jetstream;

/**
 * The API tokens page of Jetstream, with an expiry chosen by the member (#1267, ADR 0022): 30 days, 90 days (default),
 * 1 year or never. Sanctum refuses an expired token (expires_at).
 *
 * A change or a deletion of a token answers 404 for an id that is not a number, or that is not a token of the account. The
 * permissions of a new token are a list of texts, as for a change: any other value is a validation error.
 */
class ApiTokenController extends JetstreamApiTokenController
{
    /**
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        // The name of a token is one line of plain text (ADR 0069)
        $input = $request->all();
        PlainText::clean($input, ['name']);
        /** @var array{name: string, expires_in?: string, permissions?: array<array-key, string>} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'expires_in' => ['sometimes', 'string', Rule::in(Constants::API_TOKEN_EXPIRY_CHOICES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ])->validate();

        $expiresIn = $validated['expires_in'] ?? Constants::API_TOKEN_DEFAULT_EXPIRY;
        $expiresAt = $expiresIn === 'never' ? null : now()->addDays((int) $expiresIn);

        /** @var User $user */
        $user = $request->user();
        $token = $user->createToken($validated['name'], Jetstream::validPermissions($validated['permissions'] ?? []), $expiresAt);

        return back()->with('flash', [
            'token' => explode('|', $token->plainTextToken, 2)[1],
        ]);
    }

    /**
     * @param  string  $tokenId
     * @return RedirectResponse
     */
    public function update(Request $request, $tokenId)
    {
        abort_unless(self::isTokenId($tokenId), 404);

        return parent::update($request, $tokenId);
    }

    /**
     * @param  string  $tokenId
     * @return RedirectResponse
     */
    public function destroy(Request $request, $tokenId)
    {
        abort_unless(self::isTokenId($tokenId), 404);

        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('id', $tokenId)->firstOrFail()->delete();

        return back(303);
    }

    /**
     * The id of a token is a whole number that fits its column: any other value is no token
     */
    private static function isTokenId(string $tokenId): bool
    {
        return ctype_digit($tokenId) && strlen($tokenId) <= 18;
    }
}
