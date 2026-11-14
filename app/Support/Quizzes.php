<?php

namespace App\Support;

use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Http\Resources\QuizResource;
use App\Models\Page;
use App\Models\Quiz;
use App\Models\QuizChoice;
use App\Models\QuizQuestion;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What the quiz actions and pages share (#1335, ADR 0038): which pages have a quiz, how an attempt is graded, and
 * what a member reads about their own result.
 */
final class Quizzes
{
    /**
     * Only a lesson of a course space has a quiz: not a section, not a page of a page space.
     *
     * @throws BusinessException
     */
    public static function assertLesson(Page $page): void
    {
        if ($page->space->type !== KnowiiSpaceType::Course || $page->isSection()) {
            throw new BusinessException('Only the lessons of a course space have a quiz');
        }
    }

    /**
     * Grade the answers of a member: one point per question when the chosen choices are exactly the correct ones.
     * The review names, for each question, what the member chose and what was correct: it is the only place where the
     * correct choices and the explanations leave the server for a member.
     *
     * @param  array<string, list<string>>  $answers  The cuids of the chosen choices, per question cuid
     * @return array{score: int, correct: int, total: int, review: list<array{question: string, selected: list<string>, correct: bool, correct_choices: list<string>, explanation_html: string|null}>}
     */
    public static function grade(Quiz $quiz, array $answers): array
    {
        $review = [];
        $correct = 0;

        foreach ($quiz->questions as $question) {
            $expected = self::correctChoices($question);
            $selected = array_values(array_unique($answers[$question->cuid] ?? []));
            sort($selected);
            $isCorrect = $selected === $expected;
            $correct += $isCorrect ? 1 : 0;

            $review[] = [
                'question' => $question->cuid,
                'selected' => $selected,
                'correct' => $isCorrect,
                'correct_choices' => $expected,
                'explanation_html' => $question->explanation_html,
            ];
        }

        $total = count($review);

        return [
            'score' => $total === 0 ? 0 : (int) round(100 * $correct / $total),
            'correct' => $correct,
            'total' => $total,
            'review' => $review,
        ];
    }

    /**
     * The result of a member in a quiz, with the review of their last attempt; null while they did not answer.
     *
     * @return array{attempts: int, best_score: int, last_score: int, passed: bool, passed_at: string|null, review: list<array{question: string, selected: list<string>, correct: bool, correct_choices: list<string>, explanation_html: string|null}>}|null
     */
    public static function result(User $user, Quiz $quiz): ?array
    {
        $result = QuizResult::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->first();
        if ($result === null) {
            return null;
        }

        return [
            'attempts' => $result->attempts,
            'best_score' => $result->best_score,
            'last_score' => $result->last_score,
            'passed' => $result->passed_at !== null,
            'passed_at' => $result->passed_at?->toJSON(),
            'review' => self::grade($quiz, $result->last_answers)['review'],
        ];
    }

    /**
     * The quiz of a lesson as the user may read it: the managers of the course get the correct answers, a member gets
     * their own result, a reader who is not a member gets the questions only. Null when the lesson has no quiz.
     *
     * @return array{quiz: QuizResource, result: array{attempts: int, best_score: int, last_score: int, passed: bool, passed_at: string|null, review: list<array{question: string, selected: list<string>, correct: bool, correct_choices: list<string>, explanation_html: string|null}>}|null}|null
     */
    public static function for(?User $user, Page $page): ?array
    {
        $quiz = $page->quiz()->with('questions.choices')->first();
        if ($quiz === null) {
            return null;
        }

        $gate = Gate::forUser($user);

        return [
            'quiz' => $quiz->toResource()->withAnswers($user !== null && $gate->check('managePages', $page->space)),
            'result' => $user !== null && $gate->check('trackProgress', $page->space) ? self::result($user, $quiz) : null,
        ];
    }

    /**
     * @return list<string> The cuids of the correct choices of a question, sorted
     */
    private static function correctChoices(QuizQuestion $question): array
    {
        $cuids = array_values($question->choices
            ->filter(static fn (QuizChoice $choice): bool => $choice->is_correct)
            ->map(static fn (QuizChoice $choice): string => $choice->cuid)
            ->all());
        sort($cuids);

        return $cuids;
    }
}
