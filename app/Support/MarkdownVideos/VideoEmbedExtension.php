<?php

namespace App\Support\MarkdownVideos;

use App\Support\VideoEmbed;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;

/**
 * Turns a YouTube or Vimeo link alone on its line into a video (#1206, ADR 0031). The server only marks the block
 * (data attributes built from App\Support\VideoEmbed, never from the text); the browser shows the click-to-load
 * player of ADR 0029. Any other link, and a video link inside a sentence, stays a link.
 */
final class VideoEmbedExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'])
            ->addRenderer(VideoEmbedBlock::class, new VideoEmbedRenderer);
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $paragraphs = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Paragraph) {
                $paragraphs[] = $node;
            }
        }

        foreach ($paragraphs as $paragraph) {
            $video = self::videoOf($paragraph);
            if ($video === null) {
                continue;
            }
            $block = new VideoEmbedBlock($video);
            $paragraph->replaceWith($block);
            $block->appendChild($paragraph);
        }
    }

    /**
     * The video of a paragraph that holds one link and nothing else, whose text is its address (a bare URL)
     *
     * @return array{provider: 'youtube'|'vimeo', id: string, url: string, embed_url: string}|null
     */
    private static function videoOf(Paragraph $paragraph): ?array
    {
        $link = $paragraph->firstChild();
        if (! $link instanceof Link || $link !== $paragraph->lastChild()) {
            return null;
        }

        $text = $link->firstChild();
        if (! $text instanceof Text || $text !== $link->lastChild()) {
            return null;
        }

        $url = $link->getUrl();
        $literal = $text->getLiteral();
        if ($literal !== $url && preg_replace('#^https?://#', '', $url) !== $literal) {
            return null;
        }

        return VideoEmbed::parse($url);
    }
}
