<?php

namespace App\Models;

use App\Http\Resources\ChatMessageResource;
use App\Traits\GeneratesCuid;
use App\Traits\HasMentions;
use App\Traits\HasReactions;
use App\Traits\UsesUploads;
use Carbon\Carbon;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message of a chat space (#1185) or of a conversation (direct messages, #1186, ADR 0028), never both. With a
 * parent, a reply in the thread of that message (one level, chat spaces only). A deleted message keeps its row as a
 * marker, without its text.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property ?int $space_id
 * @property ?int $conversation_id
 * @property ?int $author_id
 * @property ?int $parent_id
 * @property string $body
 * @property string $body_html
 * @property int $reply_count
 * @property ?Carbon $last_reply_at
 * @property ?Carbon $edited_at
 * @property ?Carbon $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read ?Space $space
 * @property-read ?Conversation $conversation
 * @property-read ?User $author
 * @property-read ?ChatMessage $parent
 *
 * @method static \Database\Factories\ChatMessageFactory factory($count = null, $state = [])
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\ChatMessageResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(ChatMessageResource::class)]
class ChatMessage extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    use HasMentions;
    use HasReactions;
    use UsesUploads;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'community_id',
        'space_id',
        'conversation_id',
        'author_id',
        'parent_id',
        'body',
        'body_html',
        'reply_count',
        'last_reply_at',
        'edited_at',
        'deleted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'space_id',
        'conversation_id',
        'author_id',
        'parent_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'reply_count' => 'integer',
            'last_reply_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * The conversation of a direct message (#1186); null for a message of a chat space
     *
     * @return BelongsTo<Conversation, covariant $this>
     */
    final public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    final public function isDirectMessage(): bool
    {
        return $this->conversation_id !== null;
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<ChatMessage, covariant $this>
     */
    final public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The replies of the thread, oldest first
     *
     * @return HasMany<ChatMessage, covariant $this>
     */
    final public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    final public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * A direct message mentions its participants only: the others cannot read it (#1186)
     */
    protected function canMention(User $member): bool
    {
        return $this->conversation_id === null || ($this->conversation?->hasParticipant($member) ?? false);
    }

    protected function itemForeignKey(): string
    {
        return 'chat_message_id';
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
