<?php

namespace App\Traits;

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Environment;
use League\HTMLToMarkdown\Converter\TableConverter;

trait HtmlToMarkdown
{
  /**
   * Convert HTML to Markdown with specific configurations.
   *
   * Reference:
   * https://github.com/thephpleague/html-to-markdown
   *
   * @param string $html
   * @return string
   */
  public function convertHtmlToMarkdown(string $html): string
  {
    $converter = new HtmlConverter();

    // Configuration
    $converter->getConfig()->setOption('hard_break', true); // turn <br> into `\n` instead of `  \n`. i.e., GitHub Flavored Markdown (GFM) style
    $converter->getConfig()->setOption('strip_tags', true); // strip tags that don't have markdown equivalents. N.B. Strips tags, not their content
    $converter->getConfig()->setOption('remove_nodes', 'meta style script iframe'); // remove specific dom nodes
    $converter->getConfig()->setOption('preserve_comments', false); // remove comments
    $converter->getConfig()->setOption('strip_placeholder_links', true); // remove <a> that doesn't have href.

    // Add table support
    $converter->getEnvironment()->addConverter(new TableConverter());

    // Convert
    $markdown = $converter->convert($html);

    return $markdown;
  }
}
