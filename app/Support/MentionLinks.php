<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The mentions of a rendered text, for the person who reads it (ADR 0044). A mention of a member is a link to the
 * member page with the class `mention` (Markdown::renderWithMentions); a typed `@word` that names no member stays
 * text. The member page is for the members of the community, so a reader who is not a member of the community (a
 * visitor, or a signed-in person who is not a member) gets every mention as its text, with no link and no class: a
 * mention of a member and a typed `@word` look the same.
 *
 * The stored HTML keeps the links. Every resource that sends a rendered text (`body_html`, `notes_html`) calls
 * `forReader()`: this is the one place of the rule.
 */
final class MentionLinks
{
    // Memo of the request: the communities of which the reader is a member (one query per community)
    private const string REQUEST_ATTRIBUTE = 'knowii.mention-links.member-of';

    // The link of a mention: the renderer writes the class, and raw HTML of a text is escaped (ADR 0013)
    private const string LINK_PATTERN = '/<a\b[^>]*\bclass="[^"]*\bmention\b[^"]*"[^>]*>(.*?)<\/a>/su';

    /**
     * The HTML as the reader of the request gets it: unchanged for a member of the community, with the mentions as
     * text for anyone else
     */
    public static function forReader(?string $html, Request $request, int $communityId): ?string
    {
        if ($html === null || ! str_contains($html, 'mention') || self::readerIsMember($request, $communityId)) {
            return $html;
        }

        return self::asText($html);
    }

    /**
     * The HTML with every mention link turned into its text
     */
    public static function asText(string $html): string
    {
        // A text that the pattern cannot read goes out without any tag, so never with a link
        return preg_replace(self::LINK_PATTERN, '$1', $html) ?? strip_tags($html);
    }

    private static function readerIsMember(Request $request, int $communityId): bool
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        /** @var array<string, bool> $known */
        $known = $request->attributes->get(self::REQUEST_ATTRIBUTE, []);
        $key = $user->id.':'.$communityId;

        if (! array_key_exists($key, $known)) {
            // The owner, or a person with a membership: the rule of User::belongsToCommunity()
            $known[$key] = Community::query()
                ->whereKey($communityId)
                ->where(static fn (Builder $query) => $query
                    ->where('owner_id', $user->id)
                    ->orWhereIn('id', $user->communities()->select('communities.id')))
                ->exists();
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $known);
        }

        return $known[$key];
    }
}
