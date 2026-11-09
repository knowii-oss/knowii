<?php

namespace App\Actions\Pages;

use App\Constants;
use App\Contracts\Pages\CreatesPages;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Support\Markdown;
use App\Support\Positions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Add a page at the end of a page space (#1179): the owner, the admins and the moderators.
 */
class CreatePage implements CreatesPages
{
    final public function create(User $user, Space $space, array $input): Page
    {
        Log::info('Processing request to create a page', ['space' => $space->cuid]);

        Gate::forUser($user)->authorize('managePages', $space);

        /** @var array{title: string, body: string} $validated */
        $validated = Validator::make($input, self::rules(true))->validate();

        $page = DB::transaction(function () use ($space, $user, $validated) {
            $page = Page::create([
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'author_id' => $user->id,
                'title' => trim($validated['title']),
                'body' => $validated['body'],
                // Raw HTML escaped (ADR 0013)
                'body_html' => Markdown::render($validated['body']),
                'position' => Positions::next($space->pages()->reorder()),
            ]);
            // Uploaded images stay while the page shows them (#1201)
            $page->syncUploadUsages($validated['body']);

            return $page;
        });

        Log::info('Page created', ['page' => $page->cuid]);
        event(SpaceContentChanged::forPage(SpaceContentChanged::PAGE_CREATED, $page, $user->cuid));

        return $page;
    }

    /**
     * WARNING: aligned with pageFormSchema (page.schema.ts)
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_POST_TITLE, 'max:'.Constants::MAX_LENGTH_POST_TITLE],
            'body' => [$presence, 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_POST_BODY],
        ];
    }
}
