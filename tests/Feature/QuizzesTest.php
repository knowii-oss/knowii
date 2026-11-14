<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\Quiz;
use App\Models\QuizChoice;
use App\Models\QuizQuestion;
use App\Models\QuizResult;
use App\Models\Space;
use App\Models\SpaceGroup;
use Laravel\Sanctum\Sanctum;

/*
 * Lesson quizzes (#1335, ADR 0038).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->course = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Course, 'name' => 'Starter Course']);
    $this->section = Page::factory()->inSpace($this->course)->create(['kind' => KnowiiPageKind::Section, 'title' => 'Basics', 'body' => '', 'body_html' => '', 'position' => 0]);
    $this->lesson = Page::factory()->inSpace($this->course)->create(['title' => 'One', 'position' => 1]);
    $this->other = Page::factory()->inSpace($this->course)->create(['title' => 'Two', 'position' => 2]);
});

function quizPath(Page $page, bool $attempts = false, ?Space $space = null): string
{
    $space ??= $page->space;

    return ApiRoutes::path($attempts ? ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS : ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_PAGE => $page->cuid,
    ]);
}

/**
 * A quiz of two questions: a single choice (2 + 2) and a multiple choice (the even numbers).
 *
 * @return array{pass_mark: int, questions: list<array<string, mixed>>}
 */
function quizBody(int $passMark = 100): array
{
    return [
        'pass_mark' => $passMark,
        'questions' => [
            [
                'kind' => 'single',
                'prompt' => 'What is **2 + 2**?',
                'explanation' => 'Count on your fingers.',
                'choices' => [['label' => '3', 'correct' => false], ['label' => '4', 'correct' => true], ['label' => '5', 'correct' => false]],
            ],
            [
                'kind' => 'multiple',
                'prompt' => 'Which numbers are even?',
                'choices' => [['label' => '1', 'correct' => false], ['label' => '2', 'correct' => true], ['label' => '4', 'correct' => true]],
            ],
        ],
    ];
}

/**
 * The answers of an attempt: the labels to choose, per question, in the order of the quiz.
 *
 * @param  list<list<string>>  $labels
 * @return array{answers: list<array{question: string, choices: list<string>}>}
 */
function quizAnswers(Page $page, array $labels): array
{
    $questions = Quiz::query()->where('page_id', $page->id)->firstOrFail()->questions()->with('choices')->get();
    $answers = [];
    foreach ($labels as $index => $chosen) {
        $question = $questions->get($index);
        $answers[] = [
            'question' => $question->cuid,
            'choices' => $question->choices->whereIn('label', $chosen)->pluck('cuid')->values()->all(),
        ];
    }

    return ['answers' => $answers];
}

test('a manager writes the quiz of a lesson and reads it with the answers (#1335)', function () {
    $this->actingAs($this->c['moderator'])
        ->putJson(quizPath($this->lesson), quizBody(80))
        ->assertOk()
        ->assertJsonPath('type', KnowiiApiResponseType::Success->value)
        ->assertJsonPath('data.quiz.pass_mark', 80)
        ->assertJsonCount(2, 'data.quiz.questions')
        ->assertJsonPath('data.quiz.questions.0.kind', 'single')
        ->assertJsonPath('data.quiz.questions.0.prompt', 'What is **2 + 2**?')
        ->assertJsonPath('data.quiz.questions.0.prompt_html', '<p>What is <strong>2 + 2</strong>?</p>')
        ->assertJsonPath('data.quiz.questions.0.explanation_html', '<p>Count on your fingers.</p>')
        ->assertJsonPath('data.quiz.questions.0.choices.1.label', '4')
        ->assertJsonPath('data.quiz.questions.0.choices.1.is_correct', true)
        ->assertJsonPath('data.quiz.questions.1.explanation', null)
        ->assertJsonPath('data.result', null);

    expect(Quiz::query()->count())->toBe(1)
        ->and(QuizQuestion::query()->count())->toBe(2)
        ->and(QuizChoice::query()->count())->toBe(6);

    $this->actingAs($this->c['owner'])->getJson(quizPath($this->lesson))
        ->assertOk()
        ->assertJsonPath('data.quiz.questions.1.choices.2.is_correct', true);

    // The list of lessons says which ones have a quiz
    $pages = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGES, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->course->cuid]);
    $this->actingAs($this->c['member'])->getJson($pages)
        ->assertJsonPath('data.1.has_quiz', true)
        ->assertJsonPath('data.2.has_quiz', false);
});

test('a member never gets the correct answers before they answer (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();

    $response = $this->actingAs($this->c['member'])->getJson(quizPath($this->lesson))
        ->assertOk()
        ->assertJsonPath('data.quiz.questions.0.prompt_html', '<p>What is <strong>2 + 2</strong>?</p>')
        ->assertJsonPath('data.quiz.questions.0.choices.1.label', '4')
        ->assertJsonMissingPath('data.quiz.questions.0.choices.1.is_correct')
        ->assertJsonMissingPath('data.quiz.questions.0.prompt')
        ->assertJsonMissingPath('data.quiz.questions.0.explanation')
        ->assertJsonMissingPath('data.quiz.questions.0.explanation_html')
        ->assertJsonPath('data.result', null);

    expect($response->getContent())->not->toContain('is_correct')->not->toContain('Count on your fingers');
});

test('the server grades an attempt, keeps one result and marks the lesson as done on a pass (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody(100))->assertOk();
    $questions = Quiz::query()->firstOrFail()->questions()->with('choices')->get();
    $this->actingAs($this->c['member']);

    // One of two questions correct: 50 percent, below the pass mark
    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['2']]))
        ->assertOk()
        ->assertJsonPath('message', 'Quiz answered')
        ->assertJsonPath('data.result.last_score', 50)
        ->assertJsonPath('data.result.best_score', 50)
        ->assertJsonPath('data.result.attempts', 1)
        ->assertJsonPath('data.result.passed', false)
        ->assertJsonPath('data.result.review.0.correct', true)
        ->assertJsonPath('data.result.review.0.explanation_html', '<p>Count on your fingers.</p>')
        ->assertJsonPath('data.result.review.1.correct', false)
        ->assertJsonPath('data.result.review.1.selected', [$questions[1]->choices[1]->cuid])
        ->assertJsonCount(2, 'data.result.review.1.correct_choices')
        ->assertJsonPath('data.progress.completed', 0)
        // The quiz itself stays the member view
        ->assertJsonMissingPath('data.quiz.questions.0.choices.0.is_correct');
    expect(LessonProgress::query()->count())->toBe(0);

    // Every question correct: a pass, and the lesson is done
    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['2', '4']]))
        ->assertOk()
        ->assertJsonPath('data.result.last_score', 100)
        ->assertJsonPath('data.result.best_score', 100)
        ->assertJsonPath('data.result.attempts', 2)
        ->assertJsonPath('data.result.passed', true)
        ->assertJsonPath('data.progress.completed', 1)
        ->assertJsonPath('data.progress.completed_lessons', [$this->lesson->cuid]);

    // A worse attempt later keeps the best score and the pass
    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['3'], ['1']]))
        ->assertOk()
        ->assertJsonPath('data.result.last_score', 0)
        ->assertJsonPath('data.result.best_score', 100)
        ->assertJsonPath('data.result.passed', true);

    expect(QuizResult::query()->count())->toBe(1)
        ->and(QuizResult::query()->firstOrFail()->attempts)->toBe(3);

    // The result comes back with the quiz, with the review of the last attempt
    $this->getJson(quizPath($this->lesson))
        ->assertJsonPath('data.result.attempts', 3)
        ->assertJsonPath('data.result.review.0.correct', false);
    // And it is private to the member
    $this->actingAs($this->c['admin'])->getJson(quizPath($this->lesson))->assertJsonPath('data.result', null);
});

test('a multiple-choice question needs every correct choice and no other (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody(100))->assertOk();
    $this->actingAs($this->c['member']);

    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['1', '2', '4']]))
        ->assertJsonPath('data.result.review.1.correct', false)
        ->assertJsonPath('data.result.last_score', 50);
});

test('a pass mark of 0 makes the quiz a self-check: every attempt passes (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody(0))->assertOk();

    $this->actingAs($this->c['member'])
        ->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['3'], ['1']]))
        ->assertJsonPath('data.result.last_score', 0)
        ->assertJsonPath('data.result.passed', true)
        ->assertJsonPath('data.progress.completed', 1);
});

test('the default pass mark is 70 percent (#1335)', function () {
    $body = quizBody();
    unset($body['pass_mark']);

    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), $body)->assertJsonPath('data.quiz.pass_mark', Constants::DEFAULT_QUIZ_PASS_MARK);
});

test('a quiz is checked before it is saved (#1335)', function (Closure $change, string $field) {
    $body = quizBody();
    $change($body);

    $this->actingAs($this->c['admin'])
        ->putJson(quizPath($this->lesson), $body)
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonStructure(['errors' => [$field]]);
    expect(Quiz::query()->count())->toBe(0);
})->with([
    'no question' => [function (array &$body) {
        $body['questions'] = [];
    }, 'questions'],
    'too many questions' => [function (array &$body) {
        $body['questions'] = array_fill(0, Constants::MAX_QUIZ_QUESTIONS + 1, $body['questions'][0]);
    }, 'questions'],
    'a pass mark over 100' => [function (array &$body) {
        $body['pass_mark'] = 101;
    }, 'pass_mark'],
    'an unknown kind' => [function (array &$body) {
        $body['questions'][0]['kind'] = 'essay';
    }, 'questions.0.kind'],
    'no prompt' => [function (array &$body) {
        $body['questions'][0]['prompt'] = '';
    }, 'questions.0.prompt'],
    'one choice' => [function (array &$body) {
        $body['questions'][0]['choices'] = [['label' => '4', 'correct' => true]];
    }, 'questions.0.choices'],
    'nine choices' => [function (array &$body) {
        $body['questions'][1]['choices'] = array_map(fn (int $n) => ['label' => "Choice $n", 'correct' => $n === 1], range(1, Constants::MAX_QUIZ_CHOICES + 1));
    }, 'questions.1.choices'],
    'two correct choices in a single-choice question' => [function (array &$body) {
        $body['questions'][0]['choices'][0]['correct'] = true;
    }, 'questions.0.choices'],
    'no correct choice' => [function (array &$body) {
        $body['questions'][1]['choices'] = [['label' => '1', 'correct' => false], ['label' => '3', 'correct' => false]];
    }, 'questions.1.choices'],
    'the same choice twice' => [function (array &$body) {
        $body['questions'][1]['choices'][0]['label'] = ' 2 ';
    }, 'questions.1.choices'],
    'an uploaded image in a question' => [function (array &$body) {
        $body['questions'][0]['prompt'] = '![x](/storage/uploads/abc123/2026/11/def456.webp)';
    }, 'questions.0.prompt'],
]);

test('raw HTML in a question is escaped (#1335, ADR 0013)', function () {
    $body = quizBody();
    $body['questions'][0]['prompt'] = '<script>alert(1)</script> Really?';

    $html = $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), $body)->assertOk()->json('data.quiz.questions.0.prompt_html');
    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

test('the answers of an attempt are checked against the quiz (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->other), quizBody())->assertOk();
    $this->actingAs($this->c['member']);
    $good = quizAnswers($this->lesson, [['4'], ['2', '4']]);
    $foreign = quizAnswers($this->other, [['4'], ['2', '4']]);

    // No answers, a question left out, a question twice
    $this->postJson(quizPath($this->lesson, true), [])->assertStatus(400)->assertJsonStructure(['errors' => ['answers']]);
    $this->postJson(quizPath($this->lesson, true), ['answers' => [$good['answers'][0]]])->assertStatus(400)->assertJsonStructure(['errors' => ['answers']]);
    $this->postJson(quizPath($this->lesson, true), ['answers' => [$good['answers'][0], $good['answers'][0]]])->assertStatus(400);
    // A question of another quiz, a choice of another question
    $this->postJson(quizPath($this->lesson, true), ['answers' => [$good['answers'][0], $foreign['answers'][1]]])
        ->assertStatus(400)->assertJsonStructure(['errors' => ['answers.1.question']]);
    $this->postJson(quizPath($this->lesson, true), ['answers' => [$good['answers'][0], ['question' => $good['answers'][1]['question'], 'choices' => $good['answers'][0]['choices']]]])
        ->assertStatus(400)->assertJsonStructure(['errors' => ['answers.1.choices']]);
    // Two choices for a single-choice question, no choice
    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['3', '4'], ['2', '4']]))
        ->assertStatus(400)->assertJsonStructure(['errors' => ['answers.0.choices']]);
    $this->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], []]))
        ->assertStatus(400)->assertJsonStructure(['errors' => ['answers.1.choices']]);

    expect(QuizResult::query()->count())->toBe(0);
});

test('only the managers of the course write and delete a quiz (#1335)', function () {
    $this->actingAs($this->c['member'])->putJson(quizPath($this->lesson), quizBody())->assertForbidden();
    $this->actingAs($this->c['outsider'])->putJson(quizPath($this->lesson), quizBody())->assertForbidden();

    foreach (['owner', 'admin', 'moderator'] as $who) {
        $this->actingAs($this->c[$who])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    }

    $this->actingAs($this->c['member'])->deleteJson(quizPath($this->lesson))->assertForbidden();
    expect(Quiz::query()->count())->toBe(1);
});

test('outsiders read the quiz of a public course but keep no result (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $answers = quizAnswers($this->lesson, [['4'], ['2', '4']]);

    $this->actingAs($this->c['outsider'])->getJson(quizPath($this->lesson))->assertForbidden();
    $this->actingAs($this->c['outsider'])->postJson(quizPath($this->lesson, true), $answers)->assertForbidden();

    $this->course->update(['visibility' => KnowiiSpaceVisibility::Public]);
    $this->actingAs($this->c['outsider'])->getJson(quizPath($this->lesson))
        ->assertOk()
        ->assertJsonPath('data.result', null)
        ->assertJsonMissingPath('data.quiz.questions.0.choices.0.is_correct');
    $this->actingAs($this->c['outsider'])->postJson(quizPath($this->lesson, true), $answers)->assertForbidden();
    expect(QuizResult::query()->count())->toBe(0);
});

test('the quiz routes check the token abilities and the verified address (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $answers = quizAnswers($this->lesson, [['4'], ['2', '4']]);

    Sanctum::actingAs($this->c['admin'], ['pages:read']);
    $this->putJson(quizPath($this->lesson), quizBody())->assertForbidden();
    $this->deleteJson(quizPath($this->lesson))->assertForbidden();
    $this->getJson(quizPath($this->lesson))->assertOk();
    $this->postJson(quizPath($this->lesson, true), $answers)->assertOk();

    Sanctum::actingAs($this->c['admin'], ['posts:read', 'posts:write']);
    $this->getJson(quizPath($this->lesson))->assertForbidden();
    $this->postJson(quizPath($this->lesson, true), $answers)->assertForbidden();

    Sanctum::actingAs($this->c['admin'], ['pages:write']);
    $this->putJson(quizPath($this->lesson), quizBody())->assertOk();

    $this->c['member']->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->c['member'])->getJson(quizPath($this->lesson))->assertForbidden();
});

test('new questions delete the results of the members, a new pass mark alone keeps them (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody(100))->assertOk();
    $this->actingAs($this->c['member'])->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['2', '4']]))->assertOk();
    $questionCuid = QuizQuestion::query()->orderBy('position')->firstOrFail()->cuid;

    // The same questions with another pass mark: the questions and the result stay
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody(50))
        ->assertOk()
        ->assertJsonPath('data.quiz.pass_mark', 50)
        ->assertJsonPath('data.quiz.questions.0.cuid', $questionCuid);
    expect(QuizResult::query()->count())->toBe(1);

    // Another question: the result goes, the lesson stays done
    $body = quizBody(50);
    $body['questions'][0]['prompt'] = 'What is 3 + 1?';
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), $body)->assertOk();
    expect(QuizResult::query()->count())->toBe(0)
        ->and(QuizQuestion::query()->count())->toBe(2)
        ->and(QuizChoice::query()->count())->toBe(6)
        ->and(LessonProgress::query()->count())->toBe(1);
});

test('a manager deletes a quiz with its results (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $this->actingAs($this->c['member'])->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['2', '4']]))->assertOk();

    $this->actingAs($this->c['moderator'])->deleteJson(quizPath($this->lesson))->assertNoContent();

    expect(Quiz::query()->count())->toBe(0)
        ->and(QuizQuestion::query()->count())->toBe(0)
        ->and(QuizChoice::query()->count())->toBe(0)
        ->and(QuizResult::query()->count())->toBe(0)
        ->and(LessonProgress::query()->count())->toBe(1);

    $this->actingAs($this->c['moderator'])->getJson(quizPath($this->lesson))->assertNotFound();
    $this->actingAs($this->c['moderator'])->deleteJson(quizPath($this->lesson))->assertNotFound();
    $this->actingAs($this->c['member'])->postJson(quizPath($this->lesson, true), ['answers' => []])->assertNotFound();
});

test('sections and pages of page spaces have no quiz (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->section), quizBody())->assertStatus(422);
    $this->actingAs($this->c['admin'])->getJson(quizPath($this->section))->assertStatus(422);

    $pages = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Pages]);
    $page = Page::factory()->inSpace($pages)->create();
    $this->actingAs($this->c['admin'])->putJson(quizPath($page), quizBody())->assertStatus(422);
    expect(Quiz::query()->count())->toBe(0);
});

test('a lesson of another space or community is not found (#1335)', function () {
    $other = communityWithMembers();
    $otherCourse = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $other['community']->id]))->create(['type' => KnowiiSpaceType::Course]);
    $foreign = Page::factory()->inSpace($otherCourse)->create();

    $this->actingAs($this->c['admin'])->putJson(quizPath($foreign, false, $this->course), quizBody())->assertNotFound();
    $this->actingAs($this->c['admin'])->getJson(quizPath($foreign, false, $this->course))->assertNotFound();
    $this->actingAs($this->c['admin'])->postJson(quizPath($foreign, true, $this->course), ['answers' => []])->assertNotFound();
});

test('the results go when the member leaves, with the lesson and with the account (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->other), quizBody())->assertOk();
    foreach (['member', 'moderator'] as $who) {
        foreach ([$this->lesson, $this->other] as $lesson) {
            $this->actingAs($this->c[$who])->postJson(quizPath($lesson, true), quizAnswers($lesson, [['4'], ['2', '4']]))->assertOk();
        }
    }
    expect(QuizResult::query()->count())->toBe(4);

    $this->c['community']->removeUser($this->c['member']);
    expect(QuizResult::query()->where('user_id', $this->c['member']->id)->count())->toBe(0);

    $this->lesson->delete();
    expect(QuizResult::query()->where('user_id', $this->c['moderator']->id)->count())->toBe(1)
        ->and(Quiz::query()->count())->toBe(1);

    $this->c['moderator']->delete();
    expect(QuizResult::query()->count())->toBe(0);
});

test('quiz attempts are rate limited (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $answers = quizAnswers($this->lesson, [['4'], ['2', '4']]);
    $this->actingAs($this->c['member']);

    for ($i = 0; $i < Constants::QUIZ_ATTEMPTS_PER_MINUTE; $i++) {
        $this->postJson(quizPath($this->lesson, true), $answers)->assertOk();
    }
    $this->postJson(quizPath($this->lesson, true), $answers)->assertStatus(429);
});

test('the lesson page carries the quiz as its reader may see it (#1335)', function () {
    $this->actingAs($this->c['admin'])->putJson(quizPath($this->lesson), quizBody())->assertOk();
    $this->actingAs($this->c['member'])->postJson(quizPath($this->lesson, true), quizAnswers($this->lesson, [['4'], ['2']]))->assertOk();
    $community = $this->c['community'];
    $lessonRoute = fn (Page $page) => route('pages.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $page->slug]);

    $this->actingAs($this->c['member'])->get($lessonRoute($this->lesson))
        ->assertInertia(fn ($page) => $page
            ->has('quiz.quiz.questions', 2)
            ->missing('quiz.quiz.questions.0.choices.0.is_correct')
            ->where('quiz.result.last_score', 50)
            ->where('page.has_quiz', true));

    $this->actingAs($this->c['admin'])->get($lessonRoute($this->lesson))
        ->assertInertia(fn ($page) => $page->where('quiz.quiz.questions.0.choices.1.is_correct', true)->where('quiz.result', null));

    $this->actingAs($this->c['member'])->get($lessonRoute($this->other))
        ->assertInertia(fn ($page) => $page->where('quiz', null)->where('page.has_quiz', false));

    // The outline of the course marks the lessons with a quiz
    $this->actingAs($this->c['member'])
        ->get(route('spaces.show', ['communitySlug' => $community->slug, 'spaceSlug' => $this->course->slug]))
        ->assertInertia(fn ($page) => $page->where('pages.1.has_quiz', true)->where('pages.2.has_quiz', false));
});
