<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders the Markdown that members write into HTML that is safe to show (ADR 0013).
 *
 * Raw HTML is escaped (never passed through), unsafe links (javascript:, vbscript:, file:, data: except images) are
 * removed, and links to other sites open in a new tab with rel="nofollow noopener noreferrer".
 * `@username` becomes a link to the member's profile when the username is a member of the community (#1177).
 */
final class Markdown
{
    /**
     * Characters of a username (Constants::ALLOWED_USER_USERNAME_CHARACTERS_REGEX), without delimiters.
     */
    public const string USERNAME_PATTERN = '[a-zA-Z0-9][a-zA-Z0-9-]{2,35}';

    private static ?MarkdownConverter $converter = null;

    /**
     * Members that `@username` may point to during the current rendering, by lowercase username.
     *
     * @var array<string, User>|null
     */
    private static ?array $mentionable = null;

    private static ?Community $mentionCommunity = null;

    /**
     * @var array<int, User> The members mentioned during the current rendering, by id
     */
    private static array $mentioned = [];

    /**
     * Render without mentions (`@name` stays text).
     */
    public static function render(string $markdown): string
    {
        return trim(self::converter()->convert($markdown)->getContent());
    }

    /**
     * Render with the mentions of the members of a community, and return the mentioned members.
     */
    public static function renderWithMentions(string $markdown, Community $community): RenderedMarkdown
    {
        self::$mentionable = self::mentionableMembers($markdown, $community);
        self::$mentionCommunity = $community;
        self::$mentioned = [];

        try {
            $html = self::render($markdown);

            return new RenderedMarkdown($html, self::mentionedMembers());
        } finally {
            self::$mentionable = null;
            self::$mentionCommunity = null;
            self::$mentioned = [];
        }
    }

    /**
     * The members of the community whose username appears after an @ in the text (one query).
     *
     * @return array<string, User>
     */
    private static function mentionableMembers(string $markdown, Community $community): array
    {
        preg_match_all('/(?<![\w@\/])@('.self::USERNAME_PATTERN.')/', $markdown, $matches);
        $usernames = array_values(array_unique(array_map('strtolower', $matches[1])));

        if ($usernames === []) {
            return [];
        }

        $members = User::query()
            ->whereIn(\DB::raw('lower(username)'), $usernames)
            ->where(function ($query) use ($community) {
                $query->where('id', $community->owner_id)
                    ->orWhereIn('id', $community->users()->select('users.id'));
            })
            ->get();

        $result = [];
        foreach ($members as $member) {
            $result[strtolower($member->username)] = $member;
        }

        return $result;
    }

    /**
     * The members that the mention callback linked during the current rendering.
     *
     * @return list<User>
     */
    private static function mentionedMembers(): array
    {
        return array_values(self::$mentioned);
    }

    private static function linkMention(Mention $mention): ?Mention
    {
        $member = self::$mentionable[strtolower($mention->getIdentifier())] ?? null;

        if ($member === null || self::$mentionCommunity === null) {
            // Not a member of the community (or rendering without mentions): the text stays as written
            return null;
        }

        self::$mentioned[$member->id] = $member;
        $mention->setUrl(route('members.show', ['communitySlug' => self::$mentionCommunity->slug, 'username' => $member->username], false));
        $mention->data->set('attributes/class', 'mention');

        return $mention;
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $appUrl = config('app.url');
        $appHost = is_string($appUrl) ? parse_url($appUrl, PHP_URL_HOST) : null;

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => is_string($appHost) ? [$appHost] : [],
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
            'mentions' => [
                'member' => [
                    'prefix' => '@',
                    'pattern' => self::USERNAME_PATTERN,
                    'generator' => static fn (Mention $mention): ?Mention => self::linkMention($mention),
                ],
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addExtension(new MentionExtension);

        return self::$converter = new MarkdownConverter($environment);
    }
}
