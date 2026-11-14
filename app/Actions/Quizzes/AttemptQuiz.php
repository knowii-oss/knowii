<?php

namespace App\Actions\Quizzes;

use App\Constants;
use App\Contracts\Pages\TracksLessonProgress;
use App\Contracts\Quizzes\AttemptsQuizzes;
use App\Enums\KnowiiQuizQuestionKind;
use App\Exceptions\BusinessException;
use App\Models\Quiz;
use App\Models\QuizChoice;
use App\Models\QuizResult;
use App\Models\User;
use App\Support\Quizzes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Answer the quiz of a lesson (#1335, ADR 0038): the members of the community who can read the course, like the
 * progress (`SpacePolicy::trackProgress`). The server grades. Knowii keeps one result per member and quiz: the best
 * score, the last score, the last answers, the number of attempts and the first pass. A pass marks the lesson as done.
 */
class AttemptQuiz implements AttemptsQuizzes
{
    public function __construct(private readonly TracksLessonProgress $tracksLessonProgress) {}

    /**
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function attempt(User $user, Quiz $quiz, array $input): QuizResult
    {
        $page = $quiz->page;
        Gate::forUser($user)->authorize('trackProgress', $page->space);

        /** @var array{answers: list<array{question: string, choices: list<string>}>} $validated */
        $validated = Validator::make($input, [
            'answers' => ['required', 'array', 'list', 'max:'.Constants::MAX_QUIZ_QUESTIONS],
            'answers.*.question' => ['required', 'string', 'distinct'],
            'answers.*.choices' => ['required', 'array', 'list', 'min:1', 'max:'.Constants::MAX_QUIZ_CHOICES],
            'answers.*.choices.*' => ['required', 'string'],
        ])->validate();

        $quiz->loadMissing('questions.choices');
        $answers = self::answers($quiz, $validated['answers']);
        $grade = Quizzes::grade($quiz, $answers);
        $passed = $grade['score'] >= $quiz->pass_mark;

        $result = DB::transaction(static function () use ($user, $quiz, $answers, $grade, $passed): QuizResult {
            $result = QuizResult::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->lockForUpdate()->first();
            $values = [
                'best_score' => max($grade['score'], $result === null ? 0 : $result->best_score),
                'last_score' => $grade['score'],
                'last_answers' => $answers,
                'attempts' => ($result === null ? 0 : $result->attempts) + 1,
                // The first pass stays: a worse attempt later does not take it back
                'passed_at' => $result->passed_at ?? ($passed ? now() : null),
            ];

            if ($result === null) {
                return QuizResult::create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'community_id' => $quiz->community_id, ...$values]);
            }
            $result->update($values);

            return $result;
        });

        // Passing the quiz finishes the lesson (ADR 0034); a failed attempt changes nothing
        if ($passed) {
            $this->tracksLessonProgress->complete($user, $page);
        }

        // No score in the logs: it is the member's own data
        Log::info('Quiz answered', ['quiz' => $quiz->cuid, 'user' => $user->cuid]);

        return $result;
    }

    /**
     * The chosen choices per question, after the checks that need the quiz: every question once, choices of the
     * question only, one choice for a single-choice question.
     *
     * @param  list<array{question: string, choices: list<string>}>  $input
     * @return array<string, list<string>>
     *
     * @throws ValidationException
     */
    private static function answers(Quiz $quiz, array $input): array
    {
        $questions = $quiz->questions->keyBy('cuid');
        $answers = [];

        foreach ($input as $index => $answer) {
            $question = $questions->get($answer['question']);
            if ($question === null) {
                throw ValidationException::withMessages(["answers.$index.question" => 'This question is not part of the quiz.']);
            }

            $chosen = array_values(array_unique($answer['choices']));
            $known = $question->choices->map(static fn (QuizChoice $choice): string => $choice->cuid)->all();
            if (array_diff($chosen, $known) !== []) {
                throw ValidationException::withMessages(["answers.$index.choices" => 'Choose among the choices of the question.']);
            }
            if ($question->kind === KnowiiQuizQuestionKind::Single && count($chosen) !== 1) {
                throw ValidationException::withMessages(["answers.$index.choices" => 'Choose one answer.']);
            }

            $answers[$question->cuid] = $chosen;
        }

        if (count($answers) !== $questions->count()) {
            throw ValidationException::withMessages(['answers' => 'Answer every question.']);
        }

        return $answers;
    }
}
