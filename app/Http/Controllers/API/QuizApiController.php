<?php

namespace App\Http\Controllers\API;

use App\Contracts\Quizzes\AttemptsQuizzes;
use App\Contracts\Quizzes\DeletesQuizzes;
use App\Contracts\Quizzes\SavesQuizzes;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Page;
use App\Models\Quiz;
use App\Models\Space;
use App\Support\CourseProgress;
use App\Support\Quizzes;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The quiz of a lesson of a course space (#1335, ADR 0038): 0 or 1 per lesson. Members who can read the course read
 * the questions; members of the community answer and read their own result; the managers of the course write the
 * quiz and read the correct answers. The server grades: the correct choices and the explanations reach a member only
 * in the review of an attempt.
 */
class QuizApiController extends Controller
{
    use ApiResponses;

    /**
     * Get the quiz of a lesson: `quiz` (the questions and their choices) and `result` (your best score, your last
     * score, the number of attempts, whether you passed, and the review of your last attempt; null while you did not
     * answer). Managers of the course also get the Markdown sources, the correct choices and the explanations.
     * 404 when the lesson has no quiz.
     */
    final public function show(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        Gate::authorize('view', $space);
        Quizzes::assertLesson($page);

        return self::success('Quiz', null, Quizzes::for($request->user(), $page) ?? abort(404));
    }

    /**
     * Create the quiz of a lesson, or replace it as a whole (managers of the course). When the questions change, the
     * results of the members are deleted; a new pass mark alone keeps them.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('pass_mark', description: 'Percent of correct answers needed to pass, from 0 to 100 (default 70). 0 makes the quiz a self-check: every attempt passes', required: false, type: 'integer', example: 70)]
    #[BodyParameter('questions', description: 'Every question of the quiz, in order (1 to 50). Each one: `kind` (single: one correct choice; multiple: one or more), `prompt` (Markdown, up to 2 000 characters), `explanation` (optional Markdown, shown after the member answers) and `choices` (2 to 8, each with `label` and `correct`)', required: true, type: 'array', example: [['kind' => 'single', 'prompt' => 'What is 2 + 2?', 'explanation' => 'Count on your fingers.', 'choices' => [['label' => '4', 'correct' => true], ['label' => '5', 'correct' => false]]]])]
    final public function update(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $quiz = app(SavesQuizzes::class)->save($user, $page, $request->only(['pass_mark', 'questions']));

        return self::success('Quiz saved', null, ['quiz' => $quiz->toResource()->withAnswers(), 'result' => Quizzes::result($user, $quiz)]);
    }

    /**
     * Delete the quiz of a lesson, with the results of the members (managers of the course).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Page $page): Response
    {
        app(DeletesQuizzes::class)->delete($request->user() ?? throw new AuthenticationException, self::quizOf($page));

        return self::deleted();
    }

    /**
     * Answer the quiz (members of the community). The server grades: the answer has your `result` with the review of
     * this attempt (for each question: what you chose, whether it is correct, the correct choices and the
     * explanation), and your `progress` in the course. Passing marks the lesson as done. You can try again at once.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('answers', description: 'One entry per question of the quiz: `question` (its cuid) and `choices` (the cuids of the choices you pick; exactly one for a single-choice question)', required: true, type: 'array', example: [['question' => 'qqcuid1', 'choices' => ['qccuid2']]])]
    final public function attempt(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $quiz = self::quizOf($page);
        app(AttemptsQuizzes::class)->attempt($user, $quiz, $request->only(['answers']));

        return self::success('Quiz answered', null, [
            'quiz' => $quiz->toResource()->withAnswers(Gate::check('managePages', $space)),
            'result' => Quizzes::result($user, $quiz),
            'progress' => CourseProgress::for($user, $space),
        ]);
    }

    /**
     * The quiz of the lesson with its questions; 404 without one. The lesson comes from the scoped route binding.
     */
    private static function quizOf(Page $page): Quiz
    {
        $quiz = $page->quiz()->with('questions.choices')->first() ?? abort(404);

        return $quiz->setRelation('page', $page);
    }
}
