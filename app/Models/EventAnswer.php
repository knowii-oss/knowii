<?php

namespace App\Models;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Resources\EventAnswerResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\EventAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * The answer of one member to one event (#1515, ADR 0053): Yes, Replay only or No. One row per member and event; no row
 * means no answer. It says what the member plans to do, never what they did. Only the member writes it. The member, the
 * host of the event, the owner and the admins read it; nobody else does. It goes with the event, with the account of
 * the member, when the member leaves the community, and some days after the end of the event (config/retention.php).
 *
 * An answer counts only while its member can read the space of the event (scopeOfReaders): the row of a member who
 * lost the access stays, takes no place and is in no count, and counts again when the access comes back.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $event_id
 * @property int $user_id
 * @property int $community_id
 * @property ?KnowiiEventAnswer $answer Null only on a model that is not stored: a member who did not answer
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Event $event
 * @property-read User $user
 * @property-read Community $community
 *
 * @method static \Database\Factories\EventAnswerFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventAnswer newModelQuery()
 * @method static Builder<static>|EventAnswer newQuery()
 * @method static Builder<static>|EventAnswer ofReaders()
 * @method static Builder<static>|EventAnswer query()
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\EventAnswerResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(EventAnswerResource::class)]
class EventAnswer extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<EventAnswerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'event_id',
        'user_id',
        'community_id',
        'answer',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'event_id',
        'user_id',
        'community_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'answer' => KnowiiEventAnswer::class,
        ];
    }

    /**
     * @return BelongsTo<Event, covariant $this>
     */
    final public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The answers that count (#1515, ADR 0053, "The answers and the places"): the answers of the people who are members
     * of the community and can read the space of the event today. Every count of the answers goes through here: the
     * places that are left, the limit, the list and the counts of the host, and "nobody answered" of the return to
     * draft. The database decides, with one condition for all the rows: no row is loaded to be checked.
     *
     * The reading rule of a space for a member, as SpacePolicy::view decides it, written for the database: the owner or
     * a row in `community_members`; then a space that is not "groups", or the owner, an admin, or a member of an access
     * group of the space or of its space group (ADR 0025, ADR 0041). EventAnswersTest holds the two forms together.
     *
     * @param  Builder<EventAnswer>  $query
     * @return Builder<EventAnswer>
     */
    final public function scopeOfReaders(Builder $query): Builder
    {
        return $query->whereExists(static function (QueryBuilder $event): void {
            $event->selectRaw('1')
                ->from('events')
                ->join('spaces', 'spaces.id', '=', 'events.space_id')
                ->join('communities', 'communities.id', '=', 'events.community_id')
                ->leftJoin('community_members', static function (JoinClause $join): void {
                    $join->on('community_members.community_id', '=', 'communities.id')->on('community_members.user_id', '=', 'event_answers.user_id');
                })
                ->whereColumn('events.id', 'event_answers.event_id')
                // A member of the community
                ->where(static function (QueryBuilder $member): void {
                    $member->whereColumn('communities.owner_id', 'event_answers.user_id')->orWhereNotNull('community_members.id');
                })
                // Who can read the space
                ->where(static function (QueryBuilder $reader): void {
                    $reader->whereIn('spaces.visibility', [KnowiiSpaceVisibility::Public->value, KnowiiSpaceVisibility::Members->value])
                        ->orWhereColumn('communities.owner_id', 'event_answers.user_id')
                        ->orWhere('community_members.role', KnowiiCommunityMemberRole::Admin->value)
                        ->orWhereExists(static function (QueryBuilder $group): void {
                            $group->selectRaw('1')
                                ->from('access_group_members')
                                ->whereColumn('access_group_members.user_id', 'event_answers.user_id')
                                ->where(static function (QueryBuilder $opening): void {
                                    $opening->whereIn('access_group_members.access_group_id', static function (QueryBuilder $ofSpace): void {
                                        $ofSpace->select('access_group_id')->from('access_group_space')->whereColumn('access_group_space.space_id', 'spaces.id');
                                    })->orWhereIn('access_group_members.access_group_id', static function (QueryBuilder $ofSpaceGroup): void {
                                        $ofSpaceGroup->select('access_group_id')->from('access_group_space_group')->whereColumn('access_group_space_group.space_group_id', 'spaces.space_group_id');
                                    });
                                });
                        });
                });
        });
    }
}
