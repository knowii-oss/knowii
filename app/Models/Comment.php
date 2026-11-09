<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use App\Traits\HasMentions;
use App\Traits\HasReactions;
use Carbon\Carbon;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A comment on a post. A reply has a parent (a top-level comment): one level of nesting only (#1175).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $post_id
 * @property ?int $author_id
 * @property ?int $parent_id
 * @property string $body
 * @property string $body_html
 * @property ?Carbon $edited_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Post $post
 * @property-read ?User $author
 * @property-read ?Comment $parent
 * @property-read Collection<int, Comment> $replies
 * @property-read Collection<int, Reaction> $reactions
 * @property-read Collection<int, Mention> $mentions
 * @property-read ?int $mentions_count
 * @property-read ?int $reactions_count
 * @property-read ?int $replies_count
 *
 * @method static \Database\Factories\CommentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereBodyHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Comment extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use HasMentions;
    use HasReactions;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'community_id',
        'post_id',
        'author_id',
        'parent_id',
        'body',
        'body_html',
        'edited_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'post_id',
        'author_id',
        'parent_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'edited_at' => 'datetime',
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
     * @return BelongsTo<Post, covariant $this>
     */
    final public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Comment, covariant $this>
     */
    final public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, covariant $this>
     */
    final public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * The column that points to this kind of item in the reactions and mentions tables (ADR 0014).
     */
    protected function itemForeignKey(): string
    {
        return 'comment_id';
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
