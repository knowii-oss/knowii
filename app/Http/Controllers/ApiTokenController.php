<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController as JetstreamApiTokenController;
use Laravel\Jetstream\Jetstream;

/**
 * The API tokens page of Jetstream, with an expiry chosen by the member (#1267, ADR 0022): 30 days, 90 days (default),
 * 1 year or never. Sanctum refuses an expired token (expires_at).
 */
class ApiTokenController extends JetstreamApiTokenController
{
    /**
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        /** @var array{name: string, expires_in?: string} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in' => ['sometimes', 'string', Rule::in(Constants::API_TOKEN_EXPIRY_CHOICES)],
        ]);

        $expiresIn = $validated['expires_in'] ?? Constants::API_TOKEN_DEFAULT_EXPIRY;
        $expiresAt = $expiresIn === 'never' ? null : now()->addDays((int) $expiresIn);

        /** @var User $user */
        $user = $request->user();
        /** @var array<mixed> $permissions */
        $permissions = (array) $request->input('permissions', []);
        $token = $user->createToken($validated['name'], Jetstream::validPermissions($permissions), $expiresAt);

        return back()->with('flash', [
            'token' => explode('|', $token->plainTextToken, 2)[1],
        ]);
    }
}
