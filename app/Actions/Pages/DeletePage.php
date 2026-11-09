<?php

namespace App\Actions\Pages;

use App\Contracts\Pages\DeletesPages;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Delete a page (#1179). Its upload usages go by cascade; `uploads:prune` deletes the images that nothing else uses.
 */
class DeletePage implements DeletesPages
{
    final public function delete(User $user, Page $page): void
    {
        Gate::forUser($user)->authorize('managePages', $page->space);

        // Built before the delete: the event needs the space of the page
        $event = SpaceContentChanged::forPage(SpaceContentChanged::PAGE_DELETED, $page, $user->cuid);
        $page->delete();

        Log::info('Page deleted', ['page' => $page->cuid]);
        event($event);
    }
}
