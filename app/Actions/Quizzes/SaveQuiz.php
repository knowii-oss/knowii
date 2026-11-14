<?php

namespace App\Actions\Quizzes;

use App\Constants;
use App\Contracts\Quizzes\SavesQuizzes;
use App\Enums\KnowiiQuizQuestionKind;
use App\Exceptions\BusinessException;
use App\Models\Page;
use App\Models\Quiz;
use App\Models\QuizChoice;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\Markdown;
use App\Support\Quizzes;
use App\Support\UploadReferences;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Create the quiz of a lesson, or replace it as a whole (#1335, ADR 0038): the managers of the course. One request
 * carries the pass mark and every question, so the positions stay consistent. When the questions change, the results
 * of the members go: the old scores are about other questions. A new pass mark alone keeps them.
 */
class SaveQuiz implements SavesQuizzes
{
    /**
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function save(User $user, Page $page, array $input): Quiz
    {
        Gate::forUser($user)->authorize('managePages', $page->space);
        Quizzes::assertLesson($page);

        /** @var array{pass_mark?: int|string, questions: list<array{kind: string, prompt: string, explanation?: string|null, choices: list<array{label: string, correct: bool|int|string}>}>} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $questions = self::questions($validated['questions']);
        $passMark = (int) ($validated['pass_mark'] ?? Constants::DEFAULT_QUIZ_PASS_MARK);

        $quiz = DB::transaction(static function () use ($page, $questions, $passMark): Quiz {
            // One save at a time per lesson: two managers must not create two quizzes
            Page::query()->whereKey($page->id)->lockForUpdate()->first();

            $quiz = Quiz::query()->where('page_id', $page->id)->with('questions.choices')->first();
            if ($quiz === null) {
                $quiz = Quiz::create(['community_id' => $page->community_id, 'page_id' => $page->id, 'pass_mark' => $passMark]);
            } else {
                $quiz->update(['pass_mark' => $passMark]);
                if (self::stored($quiz) === $questions) {
                    return $quiz;
                }
                // Other questions: the scores of the members mean nothing any more
                $quiz->results()->delete();
                $quiz->questions()->delete();
            }

            foreach ($questions as $position => $question) {
                $row = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'position' => $position,
                    'kind' => $question['kind'],
                    'prompt' => $question['prompt'],
                    // Raw HTML escaped (ADR 0013)
                    'prompt_html' => Markdown::render($question['prompt']),
                    'explanation' => $question['explanation'],
                    'explanation_html' => $question['explanation'] === null ? null : Markdown::render($question['explanation']),
                ]);
                foreach ($question['choices'] as $choicePosition => $choice) {
                    QuizChoice::create([
                        'quiz_question_id' => $row->id,
                        'position' => $choicePosition,
                        'label' => $choice['label'],
                        'is_correct' => $choice['correct'],
                    ]);
                }
            }
            $quiz->touch();

            return $quiz;
        });

        Log::info('Quiz saved', ['quiz' => $quiz->cuid, 'page' => $page->cuid, 'questions' => count($questions)]);

        return $quiz->load('questions.choices');
    }

    /**
     * WARNING: aligned with quizFormSchema (quiz.schema.ts)
     *
     * @return array<string, list<string|object>>
     */
    public static function rules(): array
    {
        return [
            'pass_mark' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'questions' => ['required', 'array', 'list', 'min:'.Constants::MIN_QUIZ_QUESTIONS, 'max:'.Constants::MAX_QUIZ_QUESTIONS],
            'questions.*.kind' => ['required', Rule::enum(KnowiiQuizQuestionKind::class)],
            'questions.*.prompt' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_QUIZ_PROMPT],
            'questions.*.explanation' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_QUIZ_EXPLANATION],
            'questions.*.choices' => ['required', 'array', 'list', 'min:'.Constants::MIN_QUIZ_CHOICES, 'max:'.Constants::MAX_QUIZ_CHOICES],
            'questions.*.choices.*.label' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_QUIZ_CHOICE],
            'questions.*.choices.*.correct' => ['required', 'boolean'],
        ];
    }

    /**
     * The questions in the form that is stored, after the rules that a list of field rules cannot say: one correct
     * choice for a single-choice question, at least one for a multiple-choice question, no choice twice, text only.
     *
     * @param  list<array{kind: string, prompt: string, explanation?: string|null, choices: list<array{label: string, correct: bool|int|string}>}>  $input
     * @return list<array{kind: string, prompt: string, explanation: string|null, choices: list<array{label: string, correct: bool}>}>
     *
     * @throws ValidationException
     */
    private static function questions(array $input): array
    {
        $questions = [];
        $errors = [];

        foreach ($input as $index => $question) {
            $prompt = trim($question['prompt']);
            $explanation = trim($question['explanation'] ?? '');
            $choices = array_map(
                static fn (array $choice): array => ['label' => trim($choice['label']), 'correct' => filter_var($choice['correct'], FILTER_VALIDATE_BOOLEAN)],
                $question['choices'],
            );

            $correct = count(array_filter($choices, static fn (array $choice): bool => $choice['correct']));
            if ($question['kind'] === KnowiiQuizQuestionKind::Single->value && $correct !== 1) {
                $errors["questions.$index.choices"][] = 'Mark exactly one choice as correct.';
            } elseif ($correct === 0) {
                $errors["questions.$index.choices"][] = 'Mark at least one choice as correct.';
            }

            $labels = array_map(static fn (array $choice): string => mb_strtolower($choice['label']), $choices);
            if (count(array_unique($labels)) !== count($labels)) {
                $errors["questions.$index.choices"][] = 'Each choice must be different.';
            }
            if (in_array('', $labels, true)) {
                $errors["questions.$index.choices"][] = 'Give each choice a text.';
            }
            if ($prompt === '') {
                $errors["questions.$index.prompt"][] = 'Write the question.';
            }
            // Uploaded images are kept only while a post, a comment, a message or a page uses them (#1201)
            if (UploadReferences::cuidsIn($prompt.' '.$explanation) !== []) {
                $errors["questions.$index.prompt"][] = 'A question has text only: put images in the text of the lesson.';
            }

            $questions[] = [
                'kind' => $question['kind'],
                'prompt' => $prompt,
                'explanation' => $explanation === '' ? null : $explanation,
                'choices' => $choices,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $questions;
    }

    /**
     * The questions of a stored quiz, in the form of `questions()`, to see whether a save changes them
     *
     * @return list<array{kind: string, prompt: string, explanation: string|null, choices: list<array{label: string, correct: bool}>}>
     */
    private static function stored(Quiz $quiz): array
    {
        return array_values($quiz->questions->map(static fn (QuizQuestion $question): array => [
            'kind' => $question->kind->value,
            'prompt' => $question->prompt,
            'explanation' => $question->explanation,
            'choices' => array_values($question->choices->map(static fn (QuizChoice $choice): array => [
                'label' => $choice->label,
                'correct' => $choice->is_correct,
            ])->all()),
        ])->all());
    }
}
