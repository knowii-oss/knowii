<?php

namespace App\Models;

use App\Http\Resources\QuizResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The quiz of a lesson of a course space (#1335, ADR 0038): ordered questions with choices, graded by the server.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $page_id
 * @property int $pass_mark Percent of correct answers needed to pass; 0 makes the quiz a self-check
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Page $page
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizResult> $results
 *
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\QuizResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(QuizResource::class)]
class Quiz extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'page_id', 'pass_mark'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id', 'page_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['pass_mark' => 'integer'];
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The lesson of the quiz
     *
     * @return BelongsTo<Page, covariant $this>
     */
    final public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The questions, in their order
     *
     * @return HasMany<QuizQuestion, covariant $this>
     */
    final public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * One result per member who answered
     *
     * @return HasMany<QuizResult, covariant $this>
     */
    final public function results(): HasMany
    {
        return $this->hasMany(QuizResult::class);
    }
}
