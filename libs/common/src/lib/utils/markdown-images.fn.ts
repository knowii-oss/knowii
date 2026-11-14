/**
 * A Markdown image: `![alt](address)` or `![alt](address "title")`. Each part matches in one way only (no brackets
 * in the alt text, no parentheses in the address, a space before the title), so the pattern runs in linear time on
 * any text. The server counts the images of the rendered text and has the last word (MaxMarkdownImages.php).
 */
const MARKDOWN_IMAGE = /!\[[^[\]]*\]\([^()\s]+(?:\s[^()]*)?\)/g;

/**
 * The number of images in a Markdown text. A post, a comment, a message or a page holds MAX_IMAGES_PER_CONTENT at
 * most (#1207): the editor checks it before it uploads more.
 */
export function countMarkdownImages(markdown: string): number {
  return markdown.match(MARKDOWN_IMAGE)?.length ?? 0;
}

/**
 * The Markdown of an uploaded image. Brackets in the alt text would end the image syntax, so they go.
 */
export function markdownImage(alt: string, url: string): string {
  return `![${alt.replace(/[[\]]/g, '')}](${url})`;
}
