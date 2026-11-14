<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lesson of a course space that a member marked as done (#1304, ADR 0034). Private to the member: no cuid, no API
 * resource of its own (the API answers with the progress of the course, `App\Support\CourseProgress`).
 *
 * @property-read int $id
 * @property int $user_id
 * @property int $community_id
 * @property int $space_id
 * @property int $page_id
 * @property Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Page $page
 * @property-read Space $space
 */
class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'community_id', 'space_id', 'page_id', 'completed_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'user_id', 'community_id', 'space_id', 'page_id'];

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Page, covariant $this>
     */
    final public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
