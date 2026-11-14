<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A choice of a quiz question (#1335, ADR 0038). `is_correct` reaches a member only after they answered.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $quiz_question_id
 * @property int $position
 * @property string $label
 * @property bool $is_correct
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read QuizQuestion $question
 */
class QuizChoice extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'quiz_question_id', 'position', 'label', 'is_correct'];

    /**
     * The correct answer is never serialized by accident: the quiz resource adds it for the managers only
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'quiz_question_id', 'is_correct'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'position' => 'integer',
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<QuizQuestion, covariant $this>
     */
    final public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
