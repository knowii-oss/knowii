<?php

namespace App\Support\MarkdownVideos;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * `<div class="video-embed not-prose" data-video-provider="youtube" ...><p><a href="...">...</a></p></div>`: MarkdownContent
 * replaces the block with the VideoPlayer component (#1206).
 */
final class VideoEmbedRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        VideoEmbedBlock::assertInstanceOf($node);
        /** @var VideoEmbedBlock $node */
        $video = $node->video;

        return new HtmlElement('div', [
            // not-prose: the typography of the text does not apply to the player
            'class' => 'video-embed not-prose',
            'data-video-provider' => $video['provider'],
            'data-video-id' => $video['id'],
            'data-video-url' => $video['url'],
            'data-video-embed-url' => $video['embed_url'],
        ], $childRenderer->renderNodes($node->children()));
    }
}
