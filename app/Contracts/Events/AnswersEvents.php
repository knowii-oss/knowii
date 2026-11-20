<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\User;

interface AnswersEvents
{
    /**
     * Set the answer of the member to an event (#1515, ADR 0053): Yes, Replay only (when the host says that a replay will
     * exist) or No. It replaces the answer that the member gave before. Only a member who can see the event answers,
     * while it is published and not over. A new Yes is refused when the event is full.
     *
     * @param  array<mixed>  $input
     */
    public function answer(User $user, Event $event, array $input): EventAnswer;

    /**
     * Clear the answer of the member, under the same rules. Nothing happens when the member did not answer.
     */
    public function clear(User $user, Event $event): void;
}
