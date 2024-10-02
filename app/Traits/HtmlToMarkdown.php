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
   * @param string $html
   * @return string
   */
  public function convertHtmlToMarkdown(string $html): string
  {
    // Remove script and style tags and their contents before conversion
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

    // Configure the converter
    $environment = new Environment([
      'strip_tags' => true,
      'remove_nodes' => 'head meta link style script',
      'hard_break' => true,
      'preserve_comments' => false,
    ]);

    // Add table support
    $environment->addConverter(new TableConverter());

    $converter = new HtmlConverter($environment);

    // Convert HTML to Markdown
    $markdown = $converter->convert($html);
    $markdown = $this->cleanupMarkdown($markdown);

    return $markdown;
  }

  /**
   * Clean up Markdown content.
   *
   * @param string $markdown
   * @return string
   */
  public function cleanupMarkdown(string $markdown): string
  {
    // Remove any remaining links
    $markdown = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $markdown);

    // Remove any remaining images
    $markdown = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $markdown);

    // Trim extra whitespace and newlines
    $markdown = trim(preg_replace('/\s+/', ' ', $markdown));

    // Remove multiple consecutive empty lines
    $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);

    // Remove spaces at the beginning of lines
    $markdown = preg_replace('/^ +/m', '', $markdown);

    // Remove lines that only contain whitespace
    $markdown = preg_replace('/^\s*[\r\n]/m', '', $markdown);

    return trim($markdown);
  }
}
