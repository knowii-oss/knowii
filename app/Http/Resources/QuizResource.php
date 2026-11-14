<?php

namespace App\Http\Resources;

use App\Models\Quiz;
use App\Models\QuizChoice;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

/**
 * The quiz of a lesson (#1335, ADR 0038). The member view has the questions and the labels of the choices only. The
 * Markdown sources, the correct choices and the explanations are for the managers of the course: `withAnswers()`.
 * Load `questions.choices` first.
 *
 * @mixin Quiz
 */
class QuizResource extends AbstractKnowiiJsonResource
{
    private bool $serializeAnswers = false;

    /**
     * Serialize the Markdown sources, the correct choices and the explanations too (managers of the course only).
     */
    final public function withAnswers(bool $serializeAnswers = true): static
    {
        $this->serializeAnswers = $serializeAnswers;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            // Percent of correct answers needed to pass; 0: a self-check, every attempt passes
            'pass_mark' => $this->pass_mark,
            'questions' => $this->questions->map(fn (QuizQuestion $question): array => [
                'cuid' => $question->cuid,
                'position' => $question->position,
                'kind' => $question->kind,
                'prompt_html' => $question->prompt_html,
                'choices' => $question->choices->map(fn (QuizChoice $choice): array => [
                    'cuid' => $choice->cuid,
                    'position' => $choice->position,
                    'label' => $choice->label,
                    ...($this->serializeAnswers ? ['is_correct' => $choice->is_correct] : []),
                ])->values()->all(),
                ...($this->serializeAnswers ? [
                    'prompt' => $question->prompt,
                    'explanation' => $question->explanation,
                    'explanation_html' => $question->explanation_html,
                ] : []),
            ])->values()->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
