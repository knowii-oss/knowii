<?php

namespace App\Models;

use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Http\Resources\MemberTagResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\MemberTagFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label that the managers of a community put on its members, for example "Founder" or "Onboarded" (#1389, ADR 0060).
 * A short name and a color of the palette, and no other text: a tag of the managers is a note about a person. It is a
 * label and never an access rule: it opens no space and gives no right (the access groups do that, ADR 0025).
 *
 * Who sees it is its visibility: the members of the community (`members`), or only the people who manage the members
 * (`managers`, the default). Every answer reads the tags through MemberTagsOfMembers, which applies that rule; the
 * links of a member go through MemberTagAssignments only.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property string $name
 * @property KnowiiMemberTagColor $color
 * @property KnowiiMemberTagVisibility $visibility
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 *
 * @method static \Database\Factories\MemberTagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberTag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberTag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberTag query()
 *
 * @mixin \Eloquent
 */
#[UseResource(MemberTagResource::class)]
class MemberTag extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<MemberTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'name', 'color', 'visibility'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'color' => KnowiiMemberTagColor::class,
            'visibility' => KnowiiMemberTagVisibility::class,
        ];
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The members who have the tag, with the date of the link (`pivot.created_at`)
     *
     * @return BelongsToMany<User, covariant $this>
     */
    final public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_tag_members')->withPivot('created_at');
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }

    final public function isForManagers(): bool
    {
        return $this->visibility === KnowiiMemberTagVisibility::Managers;
    }

    /**
     * The name as it is stored: trimmed, and each run of spaces inside it made one space. A line break is not a space:
     * the validation refuses it.
     */
    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/[\p{Zs}]+/u', ' ', $name));
    }

    /**
     * The tag of the community with this name, whatever the case, after the same cleaning as a new name (the import of
     * #1390 names tags by their name). Null when there is none.
     */
    public static function findByName(Community $community, string $name): ?self
    {
        $name = self::cleanName($name);
        if ($name === '') {
            return null;
        }

        return self::query()->where('community_id', $community->id)->whereRaw('lower(name) = lower(?)', [$name])->first();
    }
}
