<?php

namespace App\Actions\Pages;

use App\Contracts\Pages\ReordersPages;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Put the pages of a page space in a new order (#1179): the input lists every page of the space, once.
 */
class ReorderPages implements ReordersPages
{
    final public function reorder(User $user, Space $space, array $input): void
    {
        Gate::forUser($user)->authorize('managePages', $space);

        /** @var array{pages: list<string>} $validated */
        $validated = Validator::make($input, [
            'pages' => ['required', 'array', 'list'],
            'pages.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $ids = $space->pages()->pluck('id', 'cuid');
        $order = $validated['pages'];
        if (count($order) !== $ids->count() || array_diff($order, $ids->keys()->all()) !== []) {
            throw ValidationException::withMessages(['pages' => 'List every page of the space, once.']);
        }

        DB::transaction(static function () use ($order, $ids) {
            foreach ($order as $position => $cuid) {
                DB::table('pages')->where('id', $ids[$cuid])->update(['position' => $position, 'updated_at' => now()]);
            }
        });

        Log::info('Pages reordered', ['space' => $space->cuid]);
        event(SpaceContentChanged::forPagesOrder($space));
    }
}
