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

test('removes links but keeps text', function () {
  $html = '<p>This is a <a href="https://example.com">link</a>.</p>';
  $expected = "This is a link.";

  $result = $this->converter->convertHtmlToMarkdown($html);

  expect($result)->toBe($expected);
});

test('removes images', function () {
  $html = '<p>An image: <img src="image.jpg" alt="Image"></p>';
  $expected = "An image:";

  $result = $this->converter->convertHtmlToMarkdown($html);

  expect($result)->toBe($expected);
});

test('cleans up extra whitespace', function () {
  $html = '<p>  Multiple     spaces    </p>';
  $expected = "Multiple spaces";

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
