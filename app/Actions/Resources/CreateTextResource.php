<?php

namespace App\Actions\Resources;

use App\Exceptions\TechnicalException;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\CommunityResourceCollection;
use App\Models\Resource;
use App\Models\ResourceTextArticle;
use App\Models\User;
use App\Contracts\Resources\CreatesTextResources;
use App\Traits\UrlCleanup;
use App\Traits\FetchUrl;
use App\Traits\HtmlToMarkdown;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Exceptions\BusinessException;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiResourceLevel;
use GuzzleHttp\Exception\GuzzleException;
use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;

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
      Log::debug("Could not resolve the URL", [$e]);
      throw new BusinessException("Could not resolve the URL");
    }

    Log::debug("URL to create a text resource for: ", [$finalUrl]);

    Log::debug("Checking if the URL is actually available");
    if (false === $this->isUrlAvailable($finalUrl)) {
      throw new BusinessException("The URL is not available. Cannot save it");
    }

    Log::debug("Verifying is the community already has that resource");
    if($communityResourceCollection->containsCommunityResourcePointingToResourceWithUrl($finalUrl)) {
      throw new BusinessException("Cannot add the same resource twice to the same resource collection");
    }

    Log::debug("Loading the page HTML");
    try {
      $html = $this->fetchUrl($finalUrl);
    } catch (GuzzleException $e) {
      throw new BusinessException("Could not fetch the resource content: " . $e->getMessage());
    }
    Log::debug("Page HTML loaded");

    $pageContent = [
      'content' => null,
      'title' => '',
      'abstract' => '',
      'description' => null, // TODO let users provide a description
      'ai_summary' => null, // TODO generate summary using AI
      'published_at' => null, // FIXME identify the date
      'language' => null, // FIXME identify the language
      'thumbnail_url' => null,
    ];

    Log::debug("Extracting information from the page");
    $readability = new Readability(new Configuration());
    try {
      $readability->parse($html);

      $pageContent['content'] = $readability->getContent();
      $pageContent['title'] = $readability->getTitle();
      $pageContent['abstract'] = $readability->getExcerpt(); // FIXME rename abstract to excerpt (also in DB migrations)
      $pageContent['thumbnail_url'] = $readability->getImage();

      // FIXME extract author, etc
      // for the author, will need to find a matching user profile, or create a new one

    } catch (Exception $e) {
      Log::debug("Could not extract information from the page");
    }

    try {
      $markdown = $this->convertHtmlToMarkdown($pageContent['content']);
      $markdown = trim($markdown);
      $pageContent['content'] = $markdown;
    } catch (Exception $e) {
      Log::debug("Could not convert the page content to Markdown");
      $pageContent['content'] = '';
    }

    $level = KnowiiResourceLevel::from($input['level']);

    try {
      return DB::transaction(static function () use ($user, $community, $communityResourceCollection, $finalUrl, $level, $pageContent) {
        $resourceData = [
          'name' => $pageContent['title'],
          'abstract' => $pageContent['abstract'], // FIXME rename abstract to excerpt (also in DB migrations)
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
          // FIXME should convert from Markdown to plain text before calculating
          'reading_time' => ceil(str_word_count($pageContent['content'])/ 200),
        ]);

        $communityResource = CommunityResource::create([
          'resource_id' => $resource->id,
          'community_id' => $community->id,
          'collection_id' => $communityResourceCollection->id,
          'curator_id' => $user->id,
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
