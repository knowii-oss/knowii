<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Space
 */
class SpaceResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: The fields MUST remain aligned with spaceSchema (space.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '', // Ensures the returned value is never null
            'emoji' => $this->emoji ?? '', // Ensures the returned value is never null
            'type' => $this->type,
            // Link spaces (#1294, ADR 0026): the page that the navigation opens; null for the other types
            'url' => $this->url,
            'visibility' => $this->visibility,
            'posting_policy' => $this->posting_policy,
            // The groups that open a "groups" space (#1241): cuid and name, when loaded, for the people who manage the spaces
            // only: the names of the groups are not for other members, outsiders and visitors (#1387)
            'access_groups' => $this->when(
                $this->relationLoaded('accessGroups') && $this->canManageSpaces($request),
                fn () => $this->accessGroups->map(fn (AccessGroup $group) => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all(),
            ),
            // What the members who did not choose get (#1475): null means the built-in default. For the people who manage
            // the spaces only
            'default_notification_level' => $this->when($this->canManageSpaces($request), fn () => $this->default_notification_level),
            'position' => $this->position,
            // Chat spaces in the navigation (#1185): the messages that the member did not read yet
            'unread_count' => $this->when($this->unreadCount() !== null, fn () => $this->unreadCount()),
            // The dot and the level of the navigation (#1476): set by SpaceTree for a member only, absent for a visitor and an outsider
            'has_new' => $this->when($this->hasMarker('has_new'), fn () => $this->marker('has_new')),
            'notification_level' => $this->when($this->hasMarker('notification_level'), fn () => $this->marker('notification_level')),
            'space_group_cuid' => $this->relationLoaded('spaceGroup') ? $this->spaceGroup->cuid : $this->spaceGroup()->value('cuid'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Whether the reader manages the spaces of the community: false for a visitor. Asked once per community and request.
     */
    private function canManageSpaces(Request $request): bool
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        /** @var Space $space */
        $space = $this->resource;
        $key = 'knowii.manage_spaces.'.$space->community_id;
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, Gate::forUser($user)->check('manageSpaces', $space->loadMissing('community')->community));
        }

        return $request->attributes->get($key) === true;
    }

    /**
     * Whether SpaceTree set the attribute on this space (it does for the spaces of a member of the community only)
     */
    private function hasMarker(string $key): bool
    {
        /** @var Space $space */
        $space = $this->resource;

        return array_key_exists($key, $space->getAttributes());
    }

    private function marker(string $key): bool|string|null
    {
        /** @var Space $space */
        $space = $this->resource;
        $value = $space->getAttributes()[$key] ?? null;

        return is_bool($value) || is_string($value) ? $value : null;
    }

    /**
     * Set by SpaceTree on the chat spaces of a member (#1185); null elsewhere
     */
    private function unreadCount(): ?int
    {
        /** @var Space $space */
        $space = $this->resource;
        $value = $space->getAttributes()['unread_count'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
