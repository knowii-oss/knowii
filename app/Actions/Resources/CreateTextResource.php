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
use App\Traits\ProcessHtml;
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
  use UrlCleanup, FetchUrl, HtmlToMarkdown, ProcessHtml;

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
        'name' => ['required', 'string', 'min: ' . Constants::$MIN_LENGTH_COMMUNITY_RESOURCE_NAME, 'max: ' . Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_NAME, 'regex: ' . Constants::$ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX],

        // Nullable allows empty strings to be passed in
        // Note that the CommunityResource transforms null to an empty string
        // Reference: https://laravel.com/docs/11.x/validation#a-note-on-optional-fields
        'description' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION],
        'url' => ['required', 'url'],
        'level' => ['required', Rule::enum(KnowiiResourceLevel::class)],
      ]
    )->validate();
    Log::debug('Input validated');

    $name = $input['name'];
    $description = $input['description'] ?? null;
    $level = KnowiiResourceLevel::from($input['level']);

    Log::debug("Cleaning up the URL");
    $url = $this->cleanupUrl($input['url']);

    // FIXME this part is way too slow. Should use curl instead
    try {
      // Follow redirects if needed to identify the actual resource URL
      // We don't want to store indirect links to resources, and want to minimize the chances of ending up with duplicates
      $url = $this->getFinalUrl($url);
    } catch (GuzzleException $e) {
      Log::debug("Could not resolve the URL", [$e->getMessage()]);
      throw new BusinessException("Could not resolve the URL");
    }

    Log::debug("URL to create a text resource for: ", [$url]);

    Log::debug("Checking if the URL is actually available");
    if (false === $this->isUrlAvailable($url)) {
      throw new BusinessException("The URL is not available. Cannot save it");
    }

    Log::debug("Verifying if the community already has that resource");
    if ($communityResourceCollection->containsCommunityResourcePointingToResourceWithUrl($url)) {
      throw new BusinessException("Cannot add the same resource twice to the same resource collection");
    }

    $pageContent = [
      'html' => null,
      'readable_content' => null,
      'markdown' => null,
      'title' => null,
      'description' =>  null,
      'excerpt' => '',
      'keywords' => [],
      'ai_summary' => null, // TODO generate summary using AI
      'published_at' => null,
      'modified_at' => null,
      'language' => null, // FIXME identify the language
      'cover_image' => null,
      'cover_image_alt' => null,
      // TODO add keywords, og metadata, etc
    ];

    Log::debug("Loading the page HTML");
    try {
      $pageContent['html'] = $this->fetchUrl($url);
    } catch (Exception $e) {
      Log::debug("Could not fetch the page", [$e]);
    }

    if ($pageContent['html'] !== null) {
      Log::debug("Extracting page metadata");

      $ogType = $this->getHtmlOgType($pageContent['html']);
      if($ogType !== null && $ogType !== 'article') {
        throw new BusinessException("The URL does not point to an article");
      }

      $pageContent['description'] = $this->getHtmlPageDescription($pageContent['html']);
      $pageContent['title'] = $this->getHtmlPageTitle($pageContent['html']);

      // If title is still empty, generate it from the URL
      if (empty($pageContent['title'])) {
        $urlParts = parse_url($url);
        $path = $urlParts['path'] ?? '';
        $path = trim($path, '/');
        $pathParts = explode('/', $path);
        $lastPart = end($pathParts);
        $inferredTitle = str_replace(['-', '_'], ' ', $lastPart);
        $inferredTitle = ucwords($inferredTitle);

        if (empty($inferredTitle)) {
          $pageContent['title'] = $inferredTitle;
        }
      }

      $pageContent['published_at'] = $this->getHtmlPublishedTime($pageContent['html']);
      $pageContent['modified_at'] = $this->getHtmlModifiedTime($pageContent['html']);

      $pageContent['cover_image'] = $this->getHtmlCoverImage($pageContent['html']);
      $pageContent['cover_image_alt'] = $this->getHtmlCoverImageAlt($pageContent['html']);

      $pageContent['language'] = $this->getHtmlLanguage($pageContent['html']);

      $pageContent['keywords'] = $this->getHtmlKeywords($pageContent['html']);

      // TODO continue here




      Log::debug("Trying to extract readable content from the page");
      $readability = new Readability(new Configuration());
      try {
        $readability->parse($pageContent['html']);

        $pageContent['readable_content'] = $readability->getContent();
        if ($pageContent['readable_content'] === null) {
          Log::debug("Could not extract the page content");
        } else {
          Log::debug("Successfully retrieved the page content");
        }

        // FIXME stop extracting this here
        $pageContent['excerpt'] = $readability->getExcerpt();

        $author = $readability->getAuthor();
        if ($author !== null) {
          Log::debug("Found text article author: " . $author);
          // Emit event. Should be handled by an event handler that will find or create the corresponding user profile
        }
      } catch (Exception $e) {
        Log::debug("Could not extract information from the page", [$e]);
      }
    }

    // Only try to convert the content to Markdown if we have found some
    if ($pageContent['readable_content'] !== null) {
      try {
        $markdown = $this->convertHtmlToMarkdown($pageContent['readable_content']);
        $markdown = trim($markdown);
        $pageContent['markdown'] = $markdown;
      } catch (Exception $e) {
        Log::debug("Could not convert the page's readable content to Markdown");
        $pageContent['markdown'] = null;
      }
    }

    try {


      // FIXME delete this
      dd($pageContent);

      return DB::transaction(static function () use ($user, $community, $communityResourceCollection, $url, $level, $name, $description, $pageContent) {
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
          'cover_image' => $pageContent['cover_image'],
          'cover_image_alt' => $pageContent['cover_image_alt'],
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

        $resourceTextArticle = ResourceTextArticle::findByResourceIdAndUpdateOrCreateNew($resource->id, [
          'resource_id' => $resource->id,
          'html' => $pageContent['html'],
          'markdown' => $pageContent['markdown'],
          'word_count' => str_word_count(strip_tags($pageContent['content'])),
          'reading_time' => ceil(str_word_count($pageContent['content']) / 200),
        ]);

        $communityResource = CommunityResource::create([
          'slug' => $resource->slug,
          'name' => $name, // user-provided name for this specific community resource
          'description' => $description, // user-provided description for this specific community resource
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
