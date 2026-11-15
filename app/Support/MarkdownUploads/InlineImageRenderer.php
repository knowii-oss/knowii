<?php

namespace App\Support\MarkdownUploads;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * An image inside a sentence (#1207, ADR 0040): the image renderer of CommonMark (with its rule against unsafe
 * addresses), and the size and the `srcset` of the upload when the image is one.
 */
final class InlineImageRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private readonly ImageRenderer $core;

    public function __construct()
    {
        $this->core = new ImageRenderer;
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->core->setConfiguration($configuration);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Image) {
            throw new \InvalidArgumentException('Incompatible node type: expected an image');
        }

        $element = $this->core->render($node, $childRenderer);
        $stored = UploadImagesExtension::storedImage($node);

        // An unsafe address has an empty src: it gets nothing
        if ($stored !== null && $element instanceof HtmlElement && $element->getAttribute('src') !== '') {
            foreach ($stored->attributes(StoredImage::SIZES_SINGLE) as $name => $value) {
                $element->setAttribute($name, $value);
            }
        }

        return $element;
    }
}
