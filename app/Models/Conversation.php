<?php

namespace App\Models;

use App\Http\Resources\ConversationResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation of direct messages between 2 and 10 members of a community (#1186, ADR 0028). A one-to-one
 * conversation has a direct key: the same two members always get the same conversation.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property ?int $created_by
 * @property ?string $direct_key
 * @property ?Carbon $last_message_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Collection<int, User> $participants
 * @property-read ?ChatMessage $latestMessage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCuid($value)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\ConversationResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(ConversationResource::class)]
class Conversation extends Model
{
    use GeneratesCuid;

    // WARNING: aligned with MAX_CONVERSATION_PARTICIPANTS (constants.ts)
    public const int MAX_PARTICIPANTS = 10;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'community_id',
        'created_by',
        'direct_key',
        'last_message_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'created_by',
        'direct_key',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * The key of a one-to-one conversation: the two user ids in order
     */
    public static function directKey(User $a, User $b): string
    {
        return implode(':', [min($a->id, $b->id), max($a->id, $b->id)]);
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsToMany<User, covariant $this>
     */
    final public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_message_id')
            ->withTimestamps();
    }

    /**
     * The messages, oldest first. Named after the route parameter {message}, for scoped bindings.
     *
     * @return HasMany<ChatMessage, covariant $this>
     */
    final public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The newest message, for the lists
     *
     * @return HasOne<ChatMessage, covariant $this>
     */
    final public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    final public function isDirect(): bool
    {
        return $this->direct_key !== null;
    }

    final public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
