<?php

use App\Contracts\Content\RendersContentAgain;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiQuizQuestionKind;
use App\Enums\KnowiiSpaceType;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Event as EventModel;
use App\Models\Mention;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Visus\Cuid2\Cuid2;

/*
 * The stored HTML is rendered again from the Markdown (#1207, ADR 0040)
 */

const STALE_HTML = '<p>stale</p>';

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);

    // A large upload, so that the new HTML has a srcset
    $image = imagecreatetruecolor(3000, 1500);
    $file = (string) tempnam(sys_get_temp_dir(), 'img');
    imagepng($image, $file);
    $this->upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => new UploadedFile($file, 'photo.png', 'image/png', null, true),
        'alt' => 'A photo',
    ]);
    $this->markdown = 'Hello @'.$this->c['admin']->username."\n\n![A photo]({$this->upload->url()})\n![A photo]({$this->upload->url()})";
    $this->page = Page::factory()->inSpace($this->space)->create(['body' => "![A photo]({$this->upload->url()})", 'body_html' => STALE_HTML]);
});

/**
 * One row of each kind of content, with Markdown and a stale HTML, and fixed dates
 *
 * @return array<string, array{0: string, 1: int, 2: string}> table, id, HTML column
 */
function renderAgainRows(): array
{
    $c = test()->c;
    $markdown = test()->markdown;
    $post = Post::factory()->inSpace(test()->space)->create(['author_id' => $c['member']->id, 'body' => $markdown, 'body_html' => STALE_HTML]);
    $comment = Comment::factory()->onPost($post)->create(['author_id' => $c['member']->id, 'body' => $markdown, 'body_html' => STALE_HTML]);
    $chat = ChatMessage::factory()->inSpace(test()->space)->create(['author_id' => $c['member']->id, 'body' => $markdown, 'body_html' => STALE_HTML]);
    $conversation = Conversation::create(['cuid' => (new Cuid2)->toString(), 'community_id' => $c['community']->id, 'created_by' => $c['member']->id]);
    $direct = ChatMessage::factory()->create(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $c['community']->id, 'author_id' => $c['member']->id, 'body' => $markdown, 'body_html' => STALE_HTML]);
    $quiz = Quiz::create(['cuid' => (new Cuid2)->toString(), 'community_id' => $c['community']->id, 'page_id' => test()->page->id, 'pass_mark' => 70]);
    $question = QuizQuestion::create([
        'cuid' => (new Cuid2)->toString(), 'quiz_id' => $quiz->id, 'position' => 1, 'kind' => KnowiiQuizQuestionKind::Single,
        'prompt' => 'Which one?', 'prompt_html' => STALE_HTML, 'explanation' => "This one:\n\n![A photo](".test()->upload->url().')', 'explanation_html' => STALE_HTML,
    ]);

    $event = EventModel::factory()->inSpace(test()->space)->create(['host_id' => $c['member']->id, 'body' => '![A photo]('.test()->upload->url().')', 'body_html' => STALE_HTML]);

    // Fixed dates, and an edit date, to see that they stay
    DB::table('posts')->where('id', $post->id)->update(['updated_at' => '2026-01-01 10:00:00', 'edited_at' => '2026-01-02 10:00:00']);
    DB::table('comments')->where('id', $comment->id)->update(['updated_at' => '2026-01-01 10:00:00', 'edited_at' => '2026-01-02 10:00:00']);
    DB::table('chat_messages')->whereIn('id', [$chat->id, $direct->id])->update(['updated_at' => '2026-01-01 10:00:00', 'edited_at' => '2026-01-02 10:00:00']);
    DB::table('pages')->where('id', test()->page->id)->update(['updated_at' => '2026-01-01 10:00:00']);
    DB::table('quiz_questions')->where('id', $question->id)->update(['updated_at' => '2026-01-01 10:00:00']);
    DB::table('events')->where('id', $event->id)->update(['updated_at' => '2026-01-01 10:00:00']);

    return [
        'post' => ['posts', $post->id, 'body_html'],
        'comment' => ['comments', $comment->id, 'body_html'],
        'chat message' => ['chat_messages', $chat->id, 'body_html'],
        'direct message' => ['chat_messages', $direct->id, 'body_html'],
        'page' => ['pages', test()->page->id, 'body_html'],
        'quiz prompt' => ['quiz_questions', $question->id, 'prompt_html'],
        'quiz explanation' => ['quiz_questions', $question->id, 'explanation_html'],
        // The description of an event (#1513)
        'event' => ['events', $event->id, 'body_html'],
    ];
}

function renderAgainHtml(string $table, int $id, string $column): string
{
    return (string) DB::table($table)->where('id', $id)->value($column);
}

/**
 * @param  array<string, array{0: string, 1: int, 2: string}>  $rows
 * @return array<string, array<string, string|null>>
 */
function renderAgainDates(array $rows): array
{
    $dates = [];
    foreach ($rows as $label => [$table, $id]) {
        $columns = in_array($table, ['posts', 'comments', 'chat_messages'], true) ? ['updated_at', 'edited_at'] : ['updated_at'];
        /** @var array<string, string|null> $row */
        $row = (array) DB::table($table)->where('id', $id)->first($columns);
        $dates[$label] = $row;
    }

    return $dates;
}

test('every stored HTML is rendered again from its Markdown, with the srcset of the sizes (#1207)', function () {
    $rows = renderAgainRows();

    $counts = app(RendersContentAgain::class)->renderAgain();

    foreach ($rows as [$table, $id, $column]) {
        // The prompt of the question has no image
        $html = renderAgainHtml($table, $id, $column);
        expect($html)->not->toContain('stale');
        if ($column !== 'prompt_html') {
            expect($html)->toContain('srcset="');
        }
    }
    expect(renderAgainHtml('posts', $rows['post'][1], 'body_html'))->toContain('data-image-count="2"')
        ->and($counts['posts'])->toBe(['checked' => 1, 'changed' => 1])
        ->and($counts['chat_messages'])->toBe(['checked' => 2, 'changed' => 2])
        ->and($counts['pages'])->toBe(['checked' => 1, 'changed' => 1])
        ->and($counts['quiz_questions'])->toBe(['checked' => 1, 'changed' => 1])
        ->and($counts['events'])->toBe(['checked' => 1, 'changed' => 1]);
    // The prompt has no image: the HTML is a paragraph
    expect(renderAgainHtml('quiz_questions', $rows['quiz prompt'][1], 'prompt_html'))->toBe('<p>Which one?</p>');
});

test('the mention links are rendered as they are today (#1207)', function () {
    $rows = renderAgainRows();
    $expected = '<a class="mention" href="'.route('members.show', ['communitySlug' => $this->c['community']->slug, 'username' => $this->c['admin']->username], false).'">@'.$this->c['admin']->username.'</a>';

    app(RendersContentAgain::class)->renderAgain();

    foreach (['post', 'comment', 'chat message', 'direct message'] as $label) {
        [$table, $id, $column] = $rows[$label];
        expect(renderAgainHtml($table, $id, $column))->toContain($expected);
    }
    // A page is rendered without mentions, like when it is saved
    expect(renderAgainHtml('pages', $this->page->id, 'body_html'))->not->toContain('class="mention"');
});

test('it creates no mention, sends nothing, dispatches no event and keeps the dates (#1207)', function () {
    $rows = renderAgainRows();
    $datesBefore = renderAgainDates($rows);
    $mentionsBefore = Mention::query()->count();

    Notification::fake();
    Mail::fake();
    Queue::fake();
    $events = [];
    Event::listen('*', function (string $name, array $payload) use (&$events): void {
        $first = $payload[0] ?? null;
        // Reading rows is fine; writing through a model, or broadcasting, is not
        if (preg_match('/^eloquent\.(creating|created|saving|saved|updating|updated|deleting|deleted)/', $name) === 1 || $first instanceof ShouldBroadcast) {
            $events[] = $name;
        }
    });

    app(RendersContentAgain::class)->renderAgain();

    expect($events)->toBe([])
        ->and(Mention::query()->count())->toBe($mentionsBefore)->and($mentionsBefore)->toBe(0)
        ->and(renderAgainDates($rows))->toBe($datesBefore);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Queue::assertNothingPushed();
});

test('content:render-again --dry-run counts and writes nothing, and a second real run changes nothing (#1207)', function () {
    $rows = renderAgainRows();

    $this->artisan('content:render-again', ['--dry-run' => true])
        ->expectsOutputToContain('Would change')
        ->assertSuccessful();
    foreach ($rows as [$table, $id, $column]) {
        expect(renderAgainHtml($table, $id, $column))->toBe(STALE_HTML);
    }

    $this->artisan('content:render-again')->expectsOutputToContain('Changed')->assertSuccessful();
    $afterFirst = array_map(static fn (array $row): string => renderAgainHtml(...$row), $rows);
    expect($afterFirst)->each->not->toBe(STALE_HTML);

    $counts = app(RendersContentAgain::class)->renderAgain();
    foreach ($counts as $count) {
        expect($count['changed'])->toBe(0);
    }
    expect(array_map(static fn (array $row): string => renderAgainHtml(...$row), $rows))->toBe($afterFirst);
});

test('an upload without sizes yet gets the width and height only, and the rendering is the same on a second run (#1207)', function () {
    DB::table('uploads')->update(['sizes' => null]);
    $post = Post::factory()->inSpace($this->space)->create(['body' => $this->markdown, 'body_html' => STALE_HTML]);

    app(RendersContentAgain::class)->renderAgain();

    expect(renderAgainHtml('posts', $post->id, 'body_html'))->toContain('width="2000" height="1000"')->and(renderAgainHtml('posts', $post->id, 'body_html'))->not->toContain('srcset');
});
