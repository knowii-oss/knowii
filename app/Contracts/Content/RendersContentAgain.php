<?php

namespace App\Contracts\Content;

interface RendersContentAgain
{
    /**
     * Renders again the stored HTML of member Markdown (posts, comments, chat messages, direct messages, pages, event
     * descriptions, quiz prompts and explanations) from the Markdown (#1207, ADR 0040). It changes the HTML column only: no mention, no
     * notification, no event, and `updated_at` and `edited_at` stay as they are.
     *
     * With $dryRun nothing is written.
     *
     * @return array<string, array{checked: int, changed: int}> By kind of content: the rows read, and the rows whose HTML is different now
     */
    public function renderAgain(bool $dryRun = false): array;
}
