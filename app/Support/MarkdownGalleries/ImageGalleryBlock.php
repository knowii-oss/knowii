<?php

namespace App\Support\MarkdownGalleries;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Images that follow each other with no text between them (#1207, ADR 0039). Its children are the paragraphs that
 * hold the images; the renderer writes the images itself.
 */
final class ImageGalleryBlock extends AbstractBlock {}
