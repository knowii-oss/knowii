<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post or a comment that uses an upload (#1201). Exactly one of post_id and comment_id is set (ADR 0014).
 *
 * @property-read int $id
 * @property int $upload_id
 * @property ?int $post_id
 * @property ?int $comment_id
 * @property-read Upload $upload
 */
class UploadUsage extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['upload_id', 'post_id', 'comment_id'];

    /**
     * @return BelongsTo<Upload, covariant $this>
     */
    final public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
