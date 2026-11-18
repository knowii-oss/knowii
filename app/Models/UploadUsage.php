<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item that uses an upload (#1201). Exactly one of post_id, comment_id, page_id (#1179), chat_message_id (#1185) and
 * event_id (#1513) is set (ADR 0014).
 *
 * @property-read int $id
 * @property int $upload_id
 * @property ?int $post_id
 * @property ?int $comment_id
 * @property ?int $page_id
 * @property ?int $chat_message_id
 * @property ?int $event_id
 * @property-read Upload $upload
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage whereChatMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage wherePageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadUsage whereUploadId($value)
 *
 * @mixin \Eloquent
 */
class UploadUsage extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['upload_id', 'post_id', 'comment_id', 'page_id', 'chat_message_id', 'event_id'];

    /**
     * @return BelongsTo<Upload, covariant $this>
     */
    final public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
