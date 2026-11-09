<?php

namespace App\Actions\Resources;

use App\Contracts\Resources\UpdatesCommunityResources;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Models\CommunityResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Change the type or the level of a community resource (#1246): the curator, or a member who manages the spaces.
 * A type chosen here stays when the page is captured again. Only articles keep captured content (ADR 0018).
 */
class UpdateCommunityResource implements UpdatesCommunityResources
{
    /**
     * @param  array<mixed>  $input
     */
    final public function update(User $user, CommunityResource $resource, array $input): CommunityResource
    {
        Gate::forUser($user)->authorize('update', $resource);

        /** @var array{type?: string, level?: string} $input */
        $input = Validator::make($input, [
            'type' => ['sometimes', 'required', Rule::enum(KnowiiResourceType::class)],
            'level' => ['sometimes', 'required', Rule::enum(KnowiiResourceLevel::class)],
        ])->validate();

        $changes = [];
        if (isset($input['type'])) {
            $changes['type'] = KnowiiResourceType::from($input['type']);
            $changes['type_chosen'] = true;
        }
        if (isset($input['level'])) {
            $changes['level'] = KnowiiResourceLevel::from($input['level']);
        }
        if ($changes === []) {
            return $resource;
        }

        $resource->update($changes);

        // A server capture of a page that is not an article any more goes; "Capture again" brings it back
        if ($resource->type !== KnowiiResourceType::Article && $resource->capture_status === KnowiiCaptureStatus::Captured) {
            // One by one: the model event deletes the stored images of the article
            $resource->capture()->where('source', 'server')->first()?->delete();
        }

        Log::info('Resource updated', ['resource' => $resource->cuid, 'fields' => array_keys($changes)]);

        return $resource;
    }
}
