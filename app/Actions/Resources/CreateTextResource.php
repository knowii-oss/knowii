<?php

namespace App\Actions\Resources;

use App\Constants;
use App\Contracts\Resources\CreatesTextResources;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceTextArticle;
use App\Models\Space;
use App\Models\User;
use App\Support\ResourceImages;
use App\Traits\FetchUrl;
use App\Traits\HtmlToMarkdown;
use App\Traits\ProcessHtml;
use App\Traits\UrlCleanup;
use Exception;
use fivefilters\Readability\Readability;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateTextResource implements CreatesTextResources
{
    use FetchUrl, HtmlToMarkdown, ProcessHtml, UrlCleanup;

    /**
     * Add a text article to a resources space. The posting policy of the space decides who can add one (ADR 0015).
     *
     * @throws BusinessException
     * @throws TechnicalException
     * @throws ValidationException
     * @throws AuthorizationException
     */
    final public function create(User $user, Space $space, array $input): CommunityResource
    {
        Log::info('Processing request to create a new text resource');

        Log::debug('Verifying authorizations');
        Gate::forUser($user)->authorize('contribute', $space);
        Log::debug('Authorizations verified');

        if ($space->type !== KnowiiSpaceType::Resources) {
            throw new BusinessException('Resources can only be added to resources spaces');
        }

        Log::debug('Validating the input');

        $validator = Validator::make($input, [
            // Optional: the title of the page is used when it is missing (#1062)
            'name' => ['nullable', 'string', 'min: '.Constants::MIN_LENGTH_COMMUNITY_RESOURCE_NAME, 'max: '.Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME, 'regex: '.Constants::ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX],

            // Nullable allows empty strings to be passed in
            // Note that the CommunityResource transforms null to an empty string
            // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
            'description' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION],
            'url' => ['required', 'url'],
            'level' => ['required', Rule::enum(KnowiiResourceLevel::class)],
        ]
        );

        $validator->validate();

        /** @var array{name?: string|null, description?: string|null, url: string, level: string} $input */
        $input = $validator->validated();

        Log::debug('Input validated');

        Log::debug('Verifying that the link does belong to a blacklisted domain');

        // TODO extract this list to a configuration file
        $unsupportedDomains = [
            'youtube.com', 'youtu.be', 'netflix.com', 'tiktok.com', 'vm.tiktok.com', 'twitch.tv',
            'vimeo.com', 'dailymotion.com', 'dai.ly', 'hulu.com', 'disneyplus.com', 'amazon.com',
            'primevideo.com', 'hbomax.com', 'peacocktv.com', 'paramountplus.com', 'tv.apple.com',
            'crunchyroll.com', 'bilibili.com', 'b23.tv', 'vevo.com', 'metacafe.com', 'veoh.com',
            'video.ibm.com', 'itemfix.com', 'vudu.com', 'flickr.com', 'facebook.com', 'instagram.com',
            'twitter.com', 'linkedin.com', 'snapchat.com', 'vine.co', 'rumble.com', 'wistia.com',
            'viki.com', '9gag.com', 'funnyordie.com', 'collegehumor.com', 'ted.com', 'udemy.com',
            'coursera.org', 'edx.org', 'khanacademy.org', 'skillshare.com', 'masterclass.com',
            'crackle.com', 'pluto.tv', 'tubitv.com', 'plex.tv', 'odysee.com',
        ];

        $parsedUrl = parse_url($input['url']);
        $host = $parsedUrl['host'] ?? '';
        $domain = preg_replace('/^www\./', '', $host);

        if (in_array($domain, $unsupportedDomains, true)) {
            throw new BusinessException('The URL points to a video or streaming platform. It is not a text article');
        }

        $name = blank($input['name'] ?? null) ? null : trim((string) $input['name']);
        $description = $input['description'] ?? null;

        $level = KnowiiResourceLevel::from($input['level']);

        Log::debug('Cleaning up the URL');
        $url = $this->cleanupUrl($input['url']);

        // FIXME this part is way too slow. Should force Guzzle to use curl
        try {
            // Follow redirects if needed to identify the actual resource URL
            // We don't want to store indirect links to resources, and want to minimize the chances of ending up with duplicates
            $url = $this->getFinalUrlAfterRedirects($url);
        } catch (GuzzleException $e) {
            Log::debug('Could not resolve the URL', [$e->getMessage()]);
            throw new BusinessException('Could not resolve the URL. Please, verify the link and try again');
        }

        Log::debug('URL to create a text resource for: ', [$url]);

        Log::debug('Checking if the URL is actually available');
        if ($this->isUrlAvailable($url) === false) {
            throw new BusinessException('The page is not available. Cannot save it');
        }

        Log::debug('Verifying if the space already has that resource');
        if ($space->containsResourceWithUrl($url)) {
            throw new BusinessException('This resource is already in this space');
        }

        $coverBytes = null;
        $faviconBytes = null;

        $pageContent = [
            'html' => null,
            'readable_content' => null,
            'markdown' => null,
            'title' => null,
            'description' => null,
            'excerpt' => '',
            'keywords' => [],
            'ai_summary' => null, // TODO generate summary using AI
            'published_at' => null,
            'modified_at' => null,
            'language' => null, // FIXME identify the language
            'cover_image_url' => null,
            'cover_image_alt' => null,
            'cover_image_base64' => null,
        ];

        Log::debug('Loading the page HTML');
        try {
            $pageContent['html'] = $this->fetchUrl($url);
        } catch (Exception $e) {
            Log::debug('Could not fetch the page', [$e]);
        }

        if ($pageContent['html'] !== null) {
            Log::debug('Extracting page metadata');

            $pageContent['description'] = $this->getHtmlPageDescription($pageContent['html']);
            $pageContent['title'] = $this->getHtmlPageTitle($pageContent['html']);

            $pageContent['published_at'] = $this->getHtmlPublishedTime($pageContent['html']);
            $pageContent['modified_at'] = $this->getHtmlModifiedTime($pageContent['html']);

            $pageContent['cover_image_url'] = $this->getHtmlCoverImage($pageContent['html'], $url);
            $pageContent['cover_image_alt'] = $this->getHtmlCoverImageAlt($pageContent['html']);

            $pageContent['language'] = $this->getHtmlLanguage($pageContent['html']);

            $pageContent['keywords'] = $this->getHtmlKeywords($pageContent['html']);

            // Images: downloaded once, kept by Knowii, never hotlinked (#1063, ADR 0016)
            if (! empty($pageContent['cover_image_url'])) {
                $coverBytes = $this->downloadImage($pageContent['cover_image_url']);
                $pageContent['cover_image_base64'] = $coverBytes === null ? null : base64_encode($coverBytes);
            }
            $faviconBytes = $this->downloadImage($this->getHtmlFaviconUrl($pageContent['html'], $url));

            Log::debug('Trying to extract readable content from the page');
            try {
                $article = (new Readability)->parse($pageContent['html']);

                $pageContent['readable_content'] = $article->content;
                if ($pageContent['readable_content'] === null) {
                    Log::debug('Could not extract the page content');
                } else {
                    Log::debug('Successfully retrieved the page content');
                }

                // FIXME stop extracting this here
                $pageContent['excerpt'] = $article->excerpt;

                $author = $article->byline;
                if ($author !== null) {
                    Log::debug('Found text article author: '.$author);
                    // Emit event. Should be handled by an event handler that will find or create the corresponding user profile
                }
            } catch (Exception $e) {
                Log::debug('Could not extract information from the page', [$e]);
            }
        }

        // Only try to convert the content to Markdown if we have found some
        if ($pageContent['readable_content'] !== null) {
            Log::debug('Trying to convert the readable content to Markdown');
            try {
                $markdown = $this->convertHtmlToMarkdown($pageContent['readable_content']);
                $markdown = trim($markdown);
                $pageContent['markdown'] = $markdown;
            } catch (Exception $e) {
                Log::debug("Could not convert the page's readable content to Markdown");
                $pageContent['markdown'] = null;
            }
        }

        // No title in the page, or no page at all (Browserless failed): a title made from the URL (#1062)
        if (empty($pageContent['title'])) {
            $pageContent['title'] = self::titleFromUrl($url);
        }

        // Without a name from the member, the community uses the title of the page (#1062)
        if ($name === null) {
            $title = trim((string) ($pageContent['title'] ?? ''));
            if ($title === '') {
                throw new BusinessException('Could not find the title of the page. Give the resource a name');
            }
            $name = Str::limit($title, Constants::MAX_LENGTH_COMMUNITY_RESOURCE_NAME - 1, '…');
        }

        try {
            $communityResource = DB::transaction(static function () use ($user, $space, $url, $level, $name, $description, $pageContent) {
                $resourceData = [
                    'name' => $pageContent['title'] ?? $name, // title identified by parsing the page, or user-provided one otherwise
                    'description' => $pageContent['description'] ?? $description,
                    'excerpt' => $pageContent['excerpt'],
                    'keywords' => $pageContent['keywords'],
                    'ai_summary' => $pageContent['ai_summary'],
                    'published_at' => $pageContent['published_at'],
                    'modified_at' => $pageContent['modified_at'],
                    'language' => $pageContent['language'],
                    'url' => $url,
                    'cover_image_url' => $pageContent['cover_image_url'],
                    'cover_image_alt' => $pageContent['cover_image_alt'],
                    'cover_image_base64' => $pageContent['cover_image_base64'],
                    'type' => KnowiiResourceType::TextArticle->value,
                    'level' => $level->value,
                    'is_featured' => false,
                    'view_count' => 0,
                    'share_count' => 0,
                    'last_captured_at' => now(),
                    'last_checked_at' => now(),
                    'check_failures_count' => 0,
                    'is_unavailable' => false,
                ];

                $resource = Resource::findByUrlAndUpdateOrCreateNew($url, $resourceData);

                $wordCount = null;
                $readingTime = null;

                if ($pageContent['markdown'] !== null) {
                    $wordCount = str_word_count(strip_tags($pageContent['markdown']));
                    $readingTime = ceil(str_word_count($pageContent['markdown']) / 200);
                }

                if ($wordCount === null && $pageContent['html'] !== null) {
                    $wordCount = str_word_count(strip_tags($pageContent['html']));
                    $readingTime = ceil(str_word_count($pageContent['html']) / 200);
                }

                $resourceTextArticle = ResourceTextArticle::findByResourceIdAndUpdateOrCreateNew($resource->id, [
                    'resource_id' => $resource->id,
                    'html' => $pageContent['html'],
                    'markdown' => $pageContent['markdown'],
                    'word_count' => $wordCount,
                    'reading_time' => $readingTime,
                ]);

                $communityResource = CommunityResource::create([
                    'slug' => $resource->slug,
                    'name' => $name, // user-provided name for this specific community resource
                    'description' => $description, // user-provided description for this specific community resource
                    'resource_id' => $resource->id,
                    'community_id' => $space->community_id,
                    'space_id' => $space->id,
                    'resource_text_article_id' => $resourceTextArticle->id,
                    // We associate community resources with a user profile, not a user, so that when the user is deleted, the curation information remains
                    'curator_id' => $user->profile->id,
                    'is_featured' => false,
                ]);

                return $communityResource;
            });
        } catch (Exception $e) {
            Log::warning('Could not save the resource', [$e]);
            throw new TechnicalException('Could not save the resource');
        }

        // After the commit: the files need the cuid of the resource, and a failure only loses the images
        ResourceImages::store($communityResource->resource, $coverBytes, $faviconBytes);

        return $communityResource;
    }

    /**
     * "https://example.com/blog/smart-notes_guide/" gives "Smart Notes Guide". Null without a path.
     */
    private static function titleFromUrl(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);
        $title = ucwords(str_replace(['-', '_'], ' ', (string) end($segments)));

        return trim($title) === '' ? null : trim($title);
    }
}
