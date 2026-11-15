<?php

namespace App\Rules;

use App\Constants;
use App\Support\Markdown;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A post, a comment, a message or a page holds a limited number of images (#1207): a gallery stays readable, and one
 * text cannot keep hundreds of uploads alive. The count is the number of images that the text really shows: the
 * Markdown is rendered (ADR 0013) and the `<img>` elements are counted, so no way of writing an image gets around
 * the limit, and no pattern runs on text that a member controls. The editor counts with countMarkdownImages
 * (libs/common) before it uploads; the server has the last word.
 */
final readonly class MaxMarkdownImages implements ValidationRule
{
    public static function count(string $markdown): int
    {
        // Every Markdown image starts with "![": without it, there is nothing to render
        if (! str_contains($markdown, '![')) {
            return 0;
        }

        // Raw HTML is escaped by the renderer, so every "<img " is an image of the text
        return substr_count(Markdown::render($markdown, imageSizes: false), '<img ');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::count($value) > Constants::MAX_IMAGES_PER_CONTENT) {
            $fail('Use at most '.Constants::MAX_IMAGES_PER_CONTENT.' images.');
        }
    }
}
