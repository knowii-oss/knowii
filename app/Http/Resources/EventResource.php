<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Space;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * An event of an events space (#1513, ADR 0053). The start and the end are UTC instants (ISO 8601), with the zone in
 * which the host entered them. The meeting address and the place are cut on the server (EventPolicy::viewLocation):
 * a visitor, a person who is not a member, and a member who may not read them get null and `can_view_location` false.
 * The Markdown and its HTML are large fields. The host has the cut of the authors of posts (ADR 0044, point 6).
 *
 * WARNING: aligned with eventSchema (event.schema.ts)
 *
 * @mixin Event
 */
class EventResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;
        $canViewLocation = Gate::forUser($request->user())->check('viewLocation', $event);

        return [
            'cuid' => $event->cuid,
            'title' => $event->title,
            'slug' => $event->slug,
            'state' => $event->state,
            'starts_at' => $event->starts_at->toImmutable()->utc(),
            'ends_at' => $event->ends_at->toImmutable()->utc(),
            'time_zone' => $event->time_zone,
            // Derived from the end, never stored
            'is_past' => $event->isPast(),
            'location_visibility' => $event->location_visibility,
            'can_view_location' => $canViewLocation,
            'meeting_url' => $canViewLocation ? $event->meeting_url : null,
            'place' => $canViewLocation ? $event->place : null,
            'cover' => $this->whenLoaded('cover', fn (?Upload $cover) => $cover?->toResource(), null),
            // Empty: a decorative image
            'cover_alt' => $event->cover_alt,
            // Null when the account of the host was deleted ("Deleted member")
            'host' => $this->whenLoaded('host', fn (?User $host) => $host?->toResource(AuthorResource::class), null),
            'space' => $this->whenLoaded('space', fn (Space $space) => ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name]),
            'body' => $this->serializeLargeFields ? $event->body : null,
            'body_html' => $this->serializeLargeFields ? $event->body_html : null,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,
        ];
    }
}
