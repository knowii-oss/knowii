<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
