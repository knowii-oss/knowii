<?php

namespace App\Models;

use App\Enums\KnowiiQuizQuestionKind;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question of a lesson quiz (#1335, ADR 0038). Never exposed alone: the quiz resource serializes it.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $quiz_id
 * @property int $position
 * @property KnowiiQuizQuestionKind $kind
 * @property string $prompt Markdown
 * @property string $prompt_html
 * @property ?string $explanation Markdown, shown after the member answers
 * @property ?string $explanation_html
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Quiz $quiz
 * @property-read Collection<int, QuizChoice> $choices
 */
class QuizQuestion extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'quiz_id', 'position', 'kind', 'prompt', 'prompt_html', 'explanation', 'explanation_html'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'quiz_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'kind' => KnowiiQuizQuestionKind::class,
            'position' => 'integer',
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
     * The choices, in their order
     *
     * @return HasMany<QuizChoice, covariant $this>
     */
    final public function choices(): HasMany
    {
        return $this->hasMany(QuizChoice::class)->orderBy('position')->orderBy('id');
    }
}
