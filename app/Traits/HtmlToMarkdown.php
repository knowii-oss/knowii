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
    // Configure the converter
    $environment = new Environment([
      'strip_tags' => true,
      'hard_break' => true,
      'preserve_comments' => false,
    ]);

    // Add table support
    $environment->addConverter(new TableConverter());

    $converter = new HtmlConverter($environment);

    // Convert
    $markdown = $converter->convert($html);

    return $markdown;
  }
}
