<?php

namespace App\Actions\Content;

use App\Contracts\Content\RendersContentAgain;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\QuizQuestion;
use App\Support\Markdown;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders the stored HTML of member Markdown again (#1207, ADR 0040), in chunks, so that old content gets what the
 * renderer learned since (galleries, `srcset`). Safe to run again: a row whose HTML does not change is not written.
 *
 * It writes with the query builder (no model event, no `updated_at`) and never syncs the mentions: the mentions, the
 * notifications and the broadcast events belong to the moment a member writes. The mention links are rendered as
 * they are today (`Markdown::renderWithMentions`), with the members of the community now.
 */
final class RenderContentAgain implements RendersContentAgain
{
    private const int CHUNK = 100;

    final public function renderAgain(bool $dryRun = false): array
    {
        return [
            'posts' => $this->renderAll(
                Post::query()->with('community'),
                [['body', 'body_html']],
                static fn (Post $post, string $body): string => Markdown::renderWithMentions($body, $post->community)->html,
                $dryRun,
            ),
            'comments' => $this->renderAll(
                Comment::query()->with('community'),
                [['body', 'body_html']],
                static fn (Comment $comment, string $body): string => Markdown::renderWithMentions($body, $comment->community)->html,
                $dryRun,
            ),
            // Chat messages and direct messages (the ones with a conversation)
            'chat_messages' => $this->renderAll(
                ChatMessage::query()->with('community'),
                [['body', 'body_html']],
                static fn (ChatMessage $message, string $body): string => Markdown::renderWithMentions($body, $message->community)->html,
                $dryRun,
            ),
            // Pages are rendered without mentions
            'pages' => $this->renderAll(
                Page::query(),
                [['body', 'body_html']],
                static fn (Page $page, string $body): string => Markdown::render($body),
                $dryRun,
            ),
            // The descriptions of the events (#1513), without mentions like pages
            'events' => $this->renderAll(
                Event::query(),
                [['body', 'body_html']],
                static fn (Event $event, string $body): string => $body === '' ? '' : Markdown::render($body),
                $dryRun,
            ),
            'quiz_questions' => $this->renderAll(
                QuizQuestion::query(),
                [['prompt', 'prompt_html'], ['explanation', 'explanation_html']],
                static fn (QuizQuestion $question, string $markdown): string => Markdown::render($markdown),
                $dryRun,
            ),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<array{0: string, 1: string}>  $columns  The Markdown column and the HTML column
     * @param  Closure(TModel, string): string  $render
     * @return array{checked: int, changed: int}
     */
    private function renderAll(Builder $query, array $columns, Closure $render, bool $dryRun): array
    {
        $counts = ['checked' => 0, 'changed' => 0];

        $query->orderBy($query->getModel()->getKeyName())->chunkById(self::CHUNK, function ($models) use ($columns, $render, $dryRun, &$counts) {
            foreach ($models as $model) {
                $counts['checked']++;

                $changes = [];
                foreach ($columns as [$markdownColumn, $htmlColumn]) {
                    $markdown = $model->getAttribute($markdownColumn);
                    if (! is_string($markdown)) {
                        continue;
                    }

                    $html = $render($model, $markdown);
                    if ($html !== $model->getAttribute($htmlColumn)) {
                        $changes[$htmlColumn] = $html;
                    }
                }

                if ($changes === []) {
                    continue;
                }

                $counts['changed']++;
                if (! $dryRun) {
                    // The query builder: no model event, and `updated_at` stays as it is
                    $model->newQuery()->whereKey($model->getKey())->toBase()->update($changes);
                }
            }
        });

        return $counts;
    }
}
