<?php

namespace App\Actions\Pages;

use App\Contracts\Pages\UpdatesPages;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Page;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Change the title or the text of a page (#1179). The slug stays, so links to the page keep working.
 */
class UpdatePage implements UpdatesPages
{
    final public function update(User $user, Page $page, array $input): Page
    {
        Gate::forUser($user)->authorize('managePages', $page->space);

        /** @var array{title?: string, body?: string|null, video_url?: string|null} $validated */
        $validated = Validator::make($input, CreatePage::rules(false, $page->kind))->validate();
        if ($validated === []) {
            return $page;
        }
        if (array_key_exists('video_url', $validated)) {
            CreatePage::assertVideoAllowed($page->space, $page->kind, $validated['video_url']);
        }

        DB::transaction(function () use ($page, $user, $validated) {
            $changes = ['author_id' => $user->id];
            if (isset($validated['title'])) {
                $changes['title'] = trim($validated['title']);
            }
            // A section has no text (#1188)
            if (isset($validated['body']) && ! $page->isSection()) {
                $changes['body'] = $validated['body'];
                $changes['body_html'] = Markdown::render($validated['body']);
            }
            if (array_key_exists('video_url', $validated)) {
                $changes['video_url'] = CreatePage::blankToNull($validated['video_url']);
            }
            $page->update($changes);

            if (isset($changes['body'])) {
                $page->syncUploadUsages($changes['body']);
            }
        });

        Log::info('Page updated', ['page' => $page->cuid, 'fields' => array_keys($validated)]);
        event(SpaceContentChanged::forPage(SpaceContentChanged::PAGE_UPDATED, $page, $user->cuid));

        return $page;
    }
}
