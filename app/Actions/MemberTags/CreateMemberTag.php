<?php

namespace App\Actions\MemberTags;

use App\Constants;
use App\Contracts\MemberTags\CreatesMemberTags;
use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateMemberTag implements CreatesMemberTags
{
    /**
     * Create a member tag (#1389, ADR 0060): the owner and the admins. Without a color it is gray, without a visibility
     * it is for the managers only. The log line names the community and the tag by their cuid, never the name.
     */
    final public function create(User $user, Community $community, array $input): MemberTag
    {
        Gate::forUser($user)->authorize('manageMemberTags', $community);

        MemberTagInputRules::cleanName($input);
        /** @var array{name: string, color?: string, visibility?: string} $validated */
        $validated = Validator::make($input, MemberTagInputRules::editable(true))->validate();
        MemberTagInputRules::ensureNameIsFree($community, $validated);

        try {
            $tag = DB::transaction(static function () use ($community, $validated): MemberTag {
                // Two creates at once cannot pass the limit
                Community::query()->whereKey($community->id)->lockForUpdate()->first(['id']);
                if ($community->memberTags()->count() >= Constants::MAX_MEMBER_TAGS_PER_COMMUNITY) {
                    throw new BusinessException('A community can have at most '.Constants::MAX_MEMBER_TAGS_PER_COMMUNITY.' member tags');
                }

                // Every field is set here: the answer holds the color and the visibility (data rule 7)
                return $community->memberTags()->create([
                    'name' => $validated['name'],
                    'color' => KnowiiMemberTagColor::from($validated['color'] ?? KnowiiMemberTagColor::Gray->value),
                    'visibility' => KnowiiMemberTagVisibility::from($validated['visibility'] ?? KnowiiMemberTagVisibility::Managers->value),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // The same name created at the same moment by another request
            throw ValidationException::withMessages(['name' => MemberTagInputRules::NAME_TAKEN]);
        }

        Log::info('Member tag created', ['community' => $community->cuid, 'tag' => $tag->cuid]);

        return $tag;
    }
}
