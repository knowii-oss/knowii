<?php

namespace App\Actions\Resources;

use App\Contracts\Resources\CapturesResourcePages;
use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiResourceType;
use App\Exceptions\BusinessException;
use App\Exceptions\CaptureFailedException;
use App\Models\CommunityResource;
use App\Models\ResourceCapture;
use App\Support\ArticleImages;
use App\Support\ResourceImages;
use App\Support\ResourceTypeDetector;
use App\Traits\FetchUrl;
use App\Traits\HtmlToMarkdown;
use App\Traits\ProcessHtml;
use fivefilters\Readability\Readability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The server capture of ADR 0018: a plain HTTP fetch through SafeUrl, the metadata of the page on the shared resource,
 * its cover and icon stored by Knowii (ADR 0016), and its readable content as Markdown on the community resource.
 * A page without readable content stays a link with its metadata.
 */
class CaptureResourcePage implements CapturesResourcePages
{
    use FetchUrl, HtmlToMarkdown, ProcessHtml;

    private readonly ResourceTypeDetector $detector;

    public function __construct(?ResourceTypeDetector $detector = null)
    {
        $this->detector = $detector ?? new ResourceTypeDetector;
    }

    final public function capture(CommunityResource $communityResource): void
    {
        $resource = $communityResource->resource;
        $url = (string) $resource->url;

        try {
            $page = $this->fetchPage($url);
        } catch (BusinessException $e) {
            // SafeUrl refused the address or a redirect, or the page is too large: another try gives the same answer
            throw new CaptureFailedException($e->getMessage(), 0, $e);
        }

        $status = $page['status'];
        if ($status === 404 || $status === 410) {
            throw new CaptureFailedException('The page does not exist any more');
        }
        if ($status === 401 || $status === 403) {
            throw new CaptureFailedException('The site refused to show the page to Knowii');
        }
        if ($status >= 400) {
            // 408, 429 and 5xx can pass on another try
            throw new RuntimeException("The site answered with the HTTP status {$status}");
        }

        $isHtml = str_contains($page['content_type'], 'text/html') || str_contains($page['content_type'], 'application/xhtml');
        if (! $isHtml || $page['body'] === '') {
            // A PDF, an audio or a video file: a link with its URL, typed by its content type (#1246)
            $type = $this->detector->detect(null, $url, $page['content_type'], false);
            DB::transaction(function () use ($resource, $communityResource, $type) {
                $resource->update(['type' => $type, 'last_captured_at' => now(), 'last_checked_at' => now(), 'is_unavailable' => false]);
                $this->applyType($communityResource, $type);
            });

            return;
        }

        $html = $page['body'];
        $title = $this->getHtmlPageTitle($html);
        $coverBytes = null;
        $coverUrl = $this->getHtmlCoverImage($html, $url);
        if (! empty($coverUrl)) {
            $coverBytes = $this->downloadImage($coverUrl);
        }
        $faviconBytes = $this->downloadImage($this->getHtmlFaviconUrl($html, $url));

        [$markdown, $excerpt] = $this->readableContent($html, $url);
        $type = $this->detector->detect($html, $url, $page['content_type'], $markdown !== null);

        // The images of an article that the community keeps are stored by Knowii (#1247), before the transaction
        // (they need the network)
        $fromExtension = $communityResource->capture()->first()?->source === KnowiiCaptureSource::Extension;
        $effectiveType = $communityResource->type_chosen ? $communityResource->type : $type;
        if ($markdown !== null && $effectiveType === KnowiiResourceType::Article && ! $fromExtension) {
            $markdown = ArticleImages::store($communityResource, $markdown, fn (string $imageUrl): ?string => $this->downloadImage($imageUrl));
        }

        DB::transaction(function () use ($resource, $communityResource, $html, $title, $excerpt, $markdown, $type) {
            $resource->update(array_filter([
                'name' => $title,
                'description' => $this->getHtmlPageDescription($html),
                'excerpt' => $excerpt,
                'keywords' => $this->getHtmlKeywords($html),
                'published_at' => $this->getHtmlPublishedTime($html),
                'modified_at' => $this->getHtmlModifiedTime($html),
                'language' => $this->getHtmlLanguage($html),
                'cover_image_alt' => $this->getHtmlCoverImageAlt($html),
            ], static fn ($value) => $value !== null && $value !== '' && $value !== []) + [
                'type' => $type,
                'last_captured_at' => now(),
                'last_checked_at' => now(),
                'check_failures_count' => 0,
                'is_unavailable' => false,
            ]);

            $existing = $communityResource->capture()->first();
            $this->applyType($communityResource, $type);
            if ($existing !== null && $existing->source === KnowiiCaptureSource::Extension) {
                // What a member captured in their browser is better than a plain fetch: it stays
                return;
            }

            // Only articles keep captured content (ADR 0018): a video page or a tool stays a link
            if ($markdown === null || $this->applyType($communityResource, $type) !== KnowiiResourceType::Article) {
                $existing?->delete();

                return;
            }

            ResourceCapture::query()->updateOrCreate(
                ['community_resource_id' => $communityResource->id],
                ['markdown' => $markdown, ...ResourceCapture::measure($markdown), 'source' => KnowiiCaptureSource::Server, 'captured_at' => now()],
            );
        });

        // After the commit: the files need the cuid of the resource, and a failure only loses the images
        ResourceImages::store($resource, $coverBytes, $faviconBytes);
    }

    /**
     * The community resource takes the detected type, unless its curator chose one (#1246). Returns the type that the
     * community uses.
     */
    private function applyType(CommunityResource $communityResource, KnowiiResourceType $detected): KnowiiResourceType
    {
        if ($communityResource->type_chosen) {
            return $communityResource->type;
        }
        if ($communityResource->type !== $detected) {
            $communityResource->update(['type' => $detected]);
        }

        return $detected;
    }

    /**
     * The readable part of the page as Markdown, and its excerpt. Null Markdown when the page has no readable content.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function readableContent(string $html, string $url): array
    {
        try {
            // Relative links and images become absolute: the Markdown leaves the page
            $article = (new Readability(originalURL: $url, fixRelativeURLs: true))->parse($html);
            $content = $article->content;
            $excerpt = $article->excerpt;
        } catch (Throwable $e) {
            Log::debug('No readable content in the page', ['reason' => $e->getMessage()]);

            return [null, null];
        }

        if ($content === null || trim(strip_tags($content)) === '') {
            return [null, $excerpt];
        }

        try {
            $markdown = trim($this->convertHtmlToMarkdown($content));
        } catch (Throwable $e) {
            Log::debug('The readable content could not become Markdown', ['reason' => $e->getMessage()]);

            return [null, $excerpt];
        }

        return [$markdown === '' ? null : $markdown, $excerpt];
    }
}
