<?php

namespace App\Actions\MemberTags;

use App\Contracts\MemberTags\UpdatesMemberTags;
use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateMemberTag implements UpdatesMemberTags
{
    /**
     * Rename a member tag, or change its color or its visibility (#1389, ADR 0060): the owner and the admins. Only the
     * fields of the request change. A rename keeps the cuid and the members. A change to `members` shows the tag on every
     * member who has it: the screen asks first.
     */
    final public function update(User $user, MemberTag $tag, array $input): MemberTag
    {
        $community = $tag->community;
        Gate::forUser($user)->authorize('manageMemberTags', $community);

        MemberTagInputRules::cleanName($input);
        /** @var array{name?: string, color?: string, visibility?: string} $validated */
        $validated = Validator::make($input, MemberTagInputRules::editable(false))->validate();
        MemberTagInputRules::ensureNameIsFree($community, $validated, $tag->id);

        $changes = [];
        if (array_key_exists('name', $validated)) {
            $changes['name'] = $validated['name'];
        }
        if (array_key_exists('color', $validated)) {
            $changes['color'] = KnowiiMemberTagColor::from($validated['color']);
        }
        if (array_key_exists('visibility', $validated)) {
            $changes['visibility'] = KnowiiMemberTagVisibility::from($validated['visibility']);
        }

        try {
            // In a transaction of its own: a refused statement then ends only this one
            DB::transaction(static fn () => $tag->update($changes));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => MemberTagInputRules::NAME_TAKEN]);
        }

        Log::info('Member tag updated', ['community' => $community->cuid, 'tag' => $tag->cuid, 'fields' => array_keys($changes)]);

        return $tag;
    }
}
