<?php

namespace App\Support\MarkdownGalleries;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;

/**
 * Turns the images that follow each other into a gallery (#1207, ADR 0039): one image per line, or images
 * separated only by blank lines, with no text between them. One image alone in its paragraph is a gallery of one, so
 * it opens in the viewer too. An image inside a sentence, and an image that is a link, stay as they are.
 */
final class ImageGalleryExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'])
            ->addRenderer(ImageGalleryBlock::class, new ImageGalleryRenderer);
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $paragraphs = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Paragraph && self::holdsOnlyImages($node)) {
                $paragraphs[] = $node;
            }
        }

        foreach ($paragraphs as $paragraph) {
            // Already in the gallery of the paragraph before it
            if ($paragraph->parent() instanceof ImageGalleryBlock) {
                continue;
            }

            $block = new ImageGalleryBlock;
            $paragraph->replaceWith($block);
            $block->appendChild($paragraph);

            // The paragraphs of images that follow (a blank line between images) join the same gallery
            while (($next = $block->next()) instanceof Paragraph && self::holdsOnlyImages($next)) {
                $block->appendChild($next);
            }
        }
    }

    /**
     * A paragraph with one image or more, and nothing else than line breaks and spaces
     */
    private static function holdsOnlyImages(Paragraph $paragraph): bool
    {
        $images = 0;
        foreach ($paragraph->children() as $child) {
            if ($child instanceof Image) {
                $images++;

                continue;
            }
            if ($child instanceof Newline || ($child instanceof Text && trim($child->getLiteral()) === '')) {
                continue;
            }

            return false;
        }

        return $images > 0;
    }
}
