<?php

namespace Tests\Unit;

use App\Traits\HtmlToMarkdown;

class HtmlToMarkdownTest
{
  use HtmlToMarkdown;
}

beforeEach(function () {
  $this->converter = new HtmlToMarkdownTest();
});

test('removes scripts and styles', function () {
  $html = '<script>alert("Hello");</script><style>.class{color:red;}</style><p>Content</p>';
  $expected = "Content";

  $result = $this->converter->convertHtmlToMarkdown($html);

  expect($result)->toBe($expected);
});

test('handles empty input', function () {
  $html = '';
  $expected = '';

  $result = $this->converter->convertHtmlToMarkdown($html);

  expect($result)->toBe($expected);
});

test('handles input with only whitespace', function () {
  $html = "  \n  \t  ";
  $expected = '';

  $result = $this->converter->convertHtmlToMarkdown($html);

  expect($result)->toBe($expected);
});
