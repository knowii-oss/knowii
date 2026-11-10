<?php

namespace App\Support\MarkdownVideos;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A video link alone on its line (#1206). Its child is the paragraph with the link, which stays in the HTML: without
 * JavaScript, the reader sees the link.
 */
final class VideoEmbedBlock extends AbstractBlock
{
    /**
     * @param  array{provider: 'youtube'|'vimeo', id: string, url: string, embed_url: string}  $video
     */
    public function __construct(public readonly array $video)
    {
        parent::__construct();
    }
}
