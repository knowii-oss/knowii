<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of a member in a lesson quiz (#1335, ADR 0038): one row per member and quiz, no history of attempts.
 * Private to the member: no API resource of its own (the API answers with `App\Support\Quizzes::result()`).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $user_id
 * @property int $quiz_id
 * @property int $community_id
 * @property int $best_score Percent
 * @property int $last_score Percent
 * @property array<string, list<string>> $last_answers The cuids of the chosen choices, per question cuid
 * @property int $attempts
 * @property ?Carbon $passed_at The first time the member passed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Quiz $quiz
 * @property-read User $user
 */
class QuizResult extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'user_id', 'quiz_id', 'community_id', 'best_score', 'last_score', 'last_answers', 'attempts', 'passed_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'user_id', 'quiz_id', 'community_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'best_score' => 'integer',
            'last_score' => 'integer',
            'last_answers' => 'array',
            'attempts' => 'integer',
            'passed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, covariant $this>
     */
    final public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
