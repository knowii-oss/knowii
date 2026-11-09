<?php

namespace App\Support;

use App\Models\User;

/**
 * The HTML of a Markdown text and the members it mentions.
 */
final readonly class RenderedMarkdown
{
    /**
     * @param  list<User>  $mentioned
     */
    public function __construct(public string $html, public array $mentioned) {}
}
