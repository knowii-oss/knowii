<?php

namespace App\Actions\Resources;

use App\Constants;
use App\Contracts\Resources\CreatesTextResources;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\CommunityResourceCollection;
use App\Models\Resource;
use App\Models\ResourceTextArticle;
use App\Models\User;
use App\Traits\FetchUrl;
use App\Traits\HtmlToMarkdown;
use App\Traits\UrlCleanup;
use Exception;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateTextResource implements CreatesTextResources
{
  use UrlCleanup, FetchUrl, HtmlToMarkdown;

  /**
   * Create a new resource.
   *
   * @param User $user
   * @param Community $community
   * @param CommunityResourceCollection $communityResourceCollection
   * @param array $input
   * @return CommunityResource
   * @throws BusinessException
   * @throws TechnicalException
   * @throws ValidationException
   * @throws AuthorizationException
   */
  public function create(User $user, Community $community, CommunityResourceCollection $communityResourceCollection, array $input): CommunityResource
  {
    Log::info('Processing request to create a new text resource');

    Log::debug('Verifying authorizations');
    Gate::forUser($user)->authorize('createResource', $community);
    Log::debug('Authorizations verified');

    Log::debug('Validating the input');
    Validator::make($input, [
        'url' => ['required', 'url'],
        'level' => ['required', Rule::enum(KnowiiResourceLevel::class)],
        // Nullable allows empty strings to be passed in
        // Note that the CommunityResource transforms null to an empty string
        // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
        'description' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION],
      ]
    )->validate();
    Log::debug('Input validated');

    $url = $input['url'];

    Log::debug("Cleaning up the URL");
    $cleanUrl = $this->cleanupUrl($url);

    // FIXME this part is way too slow. Should use curl instead
    try {
      // Follow redirects if needed to identify the actual resource URL
      // We don't want to store indirect links to resources, and want to minimize the chances of ending up with duplicates
      $finalUrl = $this->getFinalUrl($cleanUrl);
    } catch (GuzzleException $e) {
      Log::debug("Could not resolve the URL", [$e->getMessage()]);
      throw new BusinessException("Could not resolve the URL");
    }

    Log::debug("URL to create a text resource for: ", [$finalUrl]);

    Log::debug("Checking if the URL is actually available");
    if (false === $this->isUrlAvailable($finalUrl)) {
      throw new BusinessException("The URL is not available. Cannot save it");
    }

    Log::debug("Verifying if the community already has that resource");
    if ($communityResourceCollection->containsCommunityResourcePointingToResourceWithUrl($finalUrl)) {
      throw new BusinessException("Cannot add the same resource twice to the same resource collection");
    }

    $pageHtml = null;

    Log::debug("Loading the page HTML");
    try {
      $pageHtml = $this->fetchUrl($finalUrl);
    } catch (Exception $e) {
      //throw new BusinessException("Could not fetch the resource content: " . $e->getMessage());
      Log::debug("Could not fetch the resource content", [$e]);
    }
    Log::debug("Page HTML loaded");


    $pageContent = [
      'content' => null,
      'title' => '',
      'description' => $input['description'] ?? null, // Either use the provided description, or set to null
      'excerpt' => '',
      'ai_summary' => null, // TODO generate summary using AI
      'published_at' => null, // FIXME identify the date
      'language' => null, // FIXME identify the language
      'thumbnail_url' => null,
    ];

    if ($pageHtml !== null) {
      Log::debug("Trying to extract information from the page");
      $readability = new Readability(new Configuration());
      try {
        $readability->parse($pageHtml);

        $pageContent['content'] = $readability->getContent();
        if ($pageContent['content'] === null) {
          Log::debug("Could not extract the page content");
        } else {
          Log::debug("Successfully retrieved the page content");
        }

        $pageContent['title'] = $readability->getTitle();
        $pageContent['excerpt'] = $readability->getExcerpt();
        $pageContent['thumbnail_url'] = $readability->getImage();

        $author = $readability->getAuthor();
        if ($author !== null) {
          Log::debug("Found text article author: " . $author);
          // Emit event. Should be handled by an event handler that will find or create the corresponding user profile
        }

      } catch (Exception $e) {
        Log::debug("Could not extract information from the page");
      }
    }

    // Only try to convert the content to Markdown if we have some content
    if ($pageContent['content'] !== null) {
      try {
        $markdown = $this->convertHtmlToMarkdown($pageContent['content']);
        $markdown = trim($markdown);
        $pageContent['content'] = $markdown;
      } catch (Exception $e) {
        Log::debug("Could not convert the page content to Markdown");
        $pageContent['content'] = '';
      }
    }

    $level = KnowiiResourceLevel::from($input['level']);

    // Generate a title if one could not be found
    if ($pageContent['title'] === null || $pageContent['title'] === '') {
      // Try to extract title from HTML
      if ($pageHtml !== null) {
        preg_match('/<title>(.*?)<\/title>/i', $pageHtml, $matches);
        if (!empty($matches[1])) {
          $pageContent['title'] = trim($matches[1]);
        }
      }

      // If still empty, generate from URL
      if (empty($pageContent['title'])) {
        $urlParts = parse_url($finalUrl);
        $path = $urlParts['path'] ?? '';
        $path = trim($path, '/');
        $pathParts = explode('/', $path);
        $lastPart = end($pathParts);
        $title = str_replace(['-', '_'], ' ', $lastPart);
        $title = ucwords($title);

        if (empty($title)) {
          $title = $urlParts['host'] ?? 'Untitled';
        }

        $pageContent['title'] = $title;
      }
    }

    try {
      return DB::transaction(static function () use ($user, $community, $communityResourceCollection, $finalUrl, $level, $pageContent) {
        $resourceData = [
          'name' => $pageContent['title'],
          'description' => $pageContent['description'],
          'excerpt' => $pageContent['excerpt'],
          'ai_summary' => $pageContent['ai_summary'],
          'published_at' => $pageContent['published_at'],
          'language' => $pageContent['language'],
          'url' => $finalUrl,
          'thumbnail_url' => $pageContent['thumbnail_url'],
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

        $resource = Resource::findByUrlAndUpdateOrCreateNew($finalUrl, $resourceData);

        $resourceTextArticle = ResourceTextArticle::findByResourceIdAndUpdateOrCreateNew($resource->id, [
          'resource_id' => $resource->id,
          'content' => $pageContent['content'],
          'word_count' => str_word_count(strip_tags($pageContent['content'])),
          // TODO should convert from Markdown to plain text before calculating
          'reading_time' => ceil(str_word_count($pageContent['content']) / 200),
        ]);

        $communityResource = CommunityResource::create([
          'slug' => $resource->slug,
          'resource_id' => $resource->id,
          'community_id' => $community->id,
          'collection_id' => $communityResourceCollection->id,
          'resource_text_article_id' => $resourceTextArticle->id,
          // We associate community resources with a user profile, not a user, so that when the user is deleted, the curation information remains
          'curator_id' => $user->profile->id,
          'is_featured' => false,
        ]);

        return $communityResource;
      });
    } catch (Exception $e) {
      Log::warning("Could not save the resource", [$e]);
      throw new TechnicalException("Could not save the resource");
    }
  }
}
