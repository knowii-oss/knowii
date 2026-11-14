import { countMarkdownImages, markdownImage } from './markdown-images.fn';

describe('countMarkdownImages (#1207)', () => {
  it('counts the images of a text, not its links', () => {
    expect(countMarkdownImages('')).toBe(0);
    expect(countMarkdownImages('A [link](https://example.com) is not an image')).toBe(0);
    expect(countMarkdownImages('![One](/a.webp) text ![Two](https://example.com/b.png "Title")')).toBe(2);
    expect(countMarkdownImages('![One](/a.webp)\n![Two](/b.webp)\n\n![Three](/c.webp)')).toBe(3);
  });

  it('stays fast on a long text of image starts', () => {
    const started = performance.now();
    expect(countMarkdownImages('!['.repeat(25000))).toBe(0);
    expect(countMarkdownImages('![]('.repeat(12000))).toBe(0);
    expect(countMarkdownImages(`![](${'!'.repeat(50000)}`)).toBe(0);
    expect(performance.now() - started).toBeLessThan(500);
  });
});

describe('markdownImage (#1207)', () => {
  it('writes the image without the brackets that would end it', () => {
    expect(markdownImage('A [[chart]', '/storage/uploads/a.webp')).toBe('![A chart](/storage/uploads/a.webp)');
  });
});
