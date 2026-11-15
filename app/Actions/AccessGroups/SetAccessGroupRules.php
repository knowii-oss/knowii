<?php

namespace App\Actions\AccessGroups;

use App\Constants;
use App\Contracts\AccessGroups\SetsAccessGroupRules;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Jobs\SyncCommunityEntitlementRules;
use App\Models\AccessGroup;
use App\Models\User;
use App\Support\OidcEntitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SetAccessGroupRules implements SetsAccessGroupRules
{
    /**
     * Replace the entitlement keys of an access group (#1386, ADR 0047): "a member who holds one of these keys at the
     * sign-in provider is in this group". The keys are free text that the platform never interprets; they are checked
     * against a safe pattern. The owner and the admins (`manageAccessGroups`), in a community that the operator lists.
     * A rule that is added, changed or removed runs the sync for the members of the community in a queued job.
     */
    final public function set(User $user, AccessGroup $group, array $input): AccessGroup
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroups', $community);
        // Without the feature, or outside the listed communities, rules do not exist
        abort_unless(OidcEntitlements::allows($community), 404);

        /** @var array{entitlement_keys: list<string>} $validated */
        $validated = Validator::make($input, [
            'entitlement_keys' => ['present', 'array', 'max:'.Constants::MAX_ENTITLEMENT_RULES_PER_GROUP],
            'entitlement_keys.*' => ['string', 'max:'.Constants::MAX_LENGTH_ENTITLEMENT_KEY, 'regex:'.Constants::ENTITLEMENT_KEY_PATTERN, 'distinct'],
        ])->validate();
        $keys = $validated['entitlement_keys'];

        /** @var list<string> $current */
        $current = $group->rules()->pluck('entitlement_key')->values()->all();
        $added = array_values(array_diff($keys, $current));
        $removed = array_values(array_diff($current, $keys));
        if ($added === [] && $removed === []) {
            return $group;
        }

        DB::transaction(function () use ($group, $added, $removed): void {
            if ($removed !== []) {
                $group->rules()->whereIn('entitlement_key', $removed)->delete();
            }
            foreach ($added as $key) {
                $group->rules()->create(['entitlement_key' => $key]);
            }
        });

        Log::info('Entitlement rules of an access group set', ['community' => $community->cuid, 'group' => $group->cuid, 'added' => count($added), 'removed' => count($removed)]);
        AccessGroupsChanged::dispatch($community->cuid);
        SyncCommunityEntitlementRules::dispatch($community);

        return $group;
    }
}
