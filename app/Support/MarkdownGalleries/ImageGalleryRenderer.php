<?php

namespace App\Support\MarkdownGalleries;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;

/**
 * `<div class="image-gallery not-prose" data-image-count="6"><a class="image-gallery-item" href="..."><img ...></a>...</div>`
 * (#1207, ADR 0039). The grid is CSS only (styles.css), from the number of images. Each image is a link to its file,
 * so it works without JavaScript; MarkdownContent opens the viewer instead. From the fifth image on, the items are
 * hidden by the CSS, and the fourth one says how many more there are.
 */
final class ImageGalleryRenderer implements NodeRendererInterface
{
    /**
     * The grid shows this many images; the viewer shows them all
     */
    public const int VISIBLE_IMAGES = 4;

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        ImageGalleryBlock::assertInstanceOf($node);

        $images = [];
        foreach ($node->iterator() as $descendant) {
            // The same rule as the image renderer of CommonMark (allow_unsafe_links is off): no javascript: address
            if ($descendant instanceof Image && ! RegexHelper::isLinkPotentiallyUnsafe($descendant->getUrl())) {
                $images[] = $descendant;
            }
        }

        $count = count($images);
        $more = $count - self::VISIBLE_IMAGES;
        $items = [];
        foreach ($images as $index => $image) {
            $content = [new HtmlElement('img', [
                'src' => $image->getUrl(),
                'alt' => self::altText($image),
                'loading' => 'lazy',
                'decoding' => 'async',
            ], '', true)];

            if ($more > 0 && $index === self::VISIBLE_IMAGES - 1) {
                $content[] = new HtmlElement('span', ['class' => 'image-gallery-more', 'aria-hidden' => 'true'], '+'.$more);
                $content[] = new HtmlElement('span', ['class' => 'image-gallery-more-label'], $more === 1 ? ', and 1 more image' : ', and '.$more.' more images');
            }

            $items[] = new HtmlElement('a', ['class' => 'image-gallery-item', 'href' => $image->getUrl()], $content);
        }

        return new HtmlElement('div', [
            // not-prose: the typography of the text does not apply to the grid
            'class' => 'image-gallery not-prose',
            'data-image-count' => (string) $count,
        ], $items);
    }

    /**
     * The alternative text of an image: the text of its label, without formatting
     */
    private static function altText(Image $image): string
    {
        $text = '';
        foreach ($image->iterator() as $descendant) {
            if ($descendant instanceof StringContainerInterface) {
                $text .= $descendant->getLiteral();
            }
        }

        return $text;
    }
}
