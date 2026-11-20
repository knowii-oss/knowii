<?php

namespace App\Http\Resources;

use App\Models\EventAnswer;
use Illuminate\Http\Request;

/**
 * The answer of the reader to an event (#1515, ADR 0053): for the member who gave it, never for another member. `answer`
 * is null when the member did not answer. It names no member and no event: the route does.
 *
 * WARNING: aligned with eventAnswerSchema (event-answer.schema.ts)
 *
 * @mixin EventAnswer
 */
class EventAnswerResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var EventAnswer $answer */
        $answer = $this->resource;

        return [
            // yes, replay or no; null: no answer
            'answer' => $answer->answer,
            // When the member gave or changed the answer; null: no answer
            'updated_at' => $answer->updated_at,
        ];
    }
}
