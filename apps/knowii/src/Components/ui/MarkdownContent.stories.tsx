import type { MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, waitFor, within } from 'storybook/test';
import { MarkdownContent } from './MarkdownContent';

// Outside the application, a link of the content must not leave the story: a click on it stops the visit. It runs after
// the handler of the galleries, which opens the viewer.
function stopVisit(event: MouseEvent): void {
  if (event.target instanceof Element && event.target.closest('a')) {
    event.preventDefault();
  }
}

/**
 * A picture in the story itself (a data URL): the story loads nothing from the network
 */
function picture(sky: string, ground: string): string {
  return (
    'data:image/svg+xml,' +
    encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400"><rect width="640" height="400" fill="${sky}"/>` +
        `<rect y="260" width="640" height="140" fill="${ground}"/></svg>`,
    )
  );
}

const PICTURES = [
  { src: picture('#a9d3ea', '#4a8c3c'), alt: 'The lettuces' },
  { src: picture('#f0be8c', '#3c6432'), alt: 'The tomatoes' },
  { src: picture('#c8c8d2', '#6e5a3c'), alt: 'The leaves on the beds' },
  { src: picture('#b4dcc8', '#50783c'), alt: 'The beans' },
  { src: picture('#e6d2aa', '#64823c'), alt: 'The pumpkins' },
  { src: picture('#d2e6f0', '#3c5a32'), alt: 'The herbs' },
];

/**
 * The HTML of a gallery, as the server writes it (App\Support\MarkdownGalleries): each image is a link to its file. From
 * five images on, the fourth says how many more there are.
 */
function gallery(count: number): string {
  const items = PICTURES.slice(0, count).map((image, index) => {
    const more =
      count > 4 && index === 3
        ? `<span class="image-gallery-more" aria-hidden="true">+${count - 4}</span>` +
          `<span class="image-gallery-more-label">, and ${count - 4} more images</span>`
        : '';
    return `<a class="image-gallery-item" href="${image.src}"><img src="${image.src}" alt="${image.alt}" loading="lazy" decoding="async" />${more}</a>`;
  });
  return `<div class="image-gallery not-prose" data-image-count="${count}">${items.join('')}</div>`;
}

/**
 * The HTML of a video link alone on its line, as the server marks it (App\Support\MarkdownVideos)
 */
function videoBlock(provider: string, url: string, embedUrl: string): string {
  return (
    `<div class="video-embed not-prose" data-video-provider="${provider}" data-video-id="v1" data-video-url="${url}" ` +
    `data-video-embed-url="${embedUrl}"><p><a href="${url}">${url}</a></p></div>`
  );
}

const TEXT = `<h2>Sow the tomatoes</h2>
<p>Sow the seeds <strong>six weeks</strong> before the last frost, in a warm and <em>bright</em> place. Ask
<a href="/communities/atlas-garden/members/rose" class="mention">@rose</a> for the seeds of last year, or read the
<a href="https://example.com/seeds">guide of the seeds</a>.</p>
<ol>
<li>Fill the pots with soil.</li>
<li>Put two seeds in each pot.</li>
<li>Keep the soil wet, not soaked.</li>
</ol>
<blockquote><p>The best time to plant a tree was twenty years ago. The second best time is now.</p></blockquote>
<p>The label of each pot: <code>tomato-2026-03</code>.</p>
<pre><code>March: sow
May: plant out
</code></pre>`;

/**
 * Shows the content of a member (a post, a comment, a message): HTML that the server rendered from Markdown, with raw
 * HTML escaped (ADR 0013). It is the only component that sets inner HTML: never give it HTML from another source. A
 * video link alone on its line becomes the player that loads nothing before Play (#1206). An image of a gallery opens
 * in the viewer (#1207).
 *
 * Accessibility: the HTML keeps its meaning (headings, lists, quotes, links). Links and mentions use
 * `secondary-text`. The images of a gallery are links, so the keyboard reaches them, and Enter opens the viewer; the
 * focus goes back to the image after the viewer closes. A click with a modifier key opens the file as the browser does.
 */
const meta = {
  component: MarkdownContent,
  render: (args) => (
    <div onClick={stopVisit}>
      <MarkdownContent {...args} />
    </div>
  ),
  args: {
    html: TEXT,
  },
} satisfies Meta<typeof MarkdownContent>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Text: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('heading', { level: 2, name: 'Sow the tomatoes' })).toBeInTheDocument();
    await expect(canvas.getByRole('link', { name: '@rose' })).toHaveClass('mention');
    await expect(canvas.getByRole('link', { name: 'guide of the seeds' })).toHaveAttribute('href', 'https://example.com/seeds');
    await expect(canvas.getAllByRole('listitem')).toHaveLength(3);
    await expect(canvas.getByText(/The best time to plant a tree/).closest('blockquote')).not.toBeNull();
  },
};

/**
 * The keyboard path of a gallery: Tab reaches an image, Enter opens it in the viewer, Escape closes it, and the focus
 * goes back to the image
 */
export const Gallery: Story = {
  args: { html: `<p>The beds this year:</p>${gallery(3)}` },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const page = within(canvasElement.ownerDocument.body);
    const tomatoes = canvas.getByRole('link', { name: 'The tomatoes' });

    await step('Enter on the second image opens it in the viewer', async () => {
      await userEvent.tab();
      await userEvent.tab();
      await expect(tomatoes).toHaveFocus();
      await userEvent.keyboard('{Enter}');
      const viewer = await page.findByRole('dialog', { name: 'Image 2 of 3' });
      // The small image of the grid has the same name: look in the viewer
      await expect(within(viewer).getByRole('img', { name: 'The tomatoes' })).toBeInTheDocument();
    });

    await step('Escape closes the viewer, and the focus goes back to the image', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
      await waitFor(() => expect(tomatoes).toHaveFocus());
    });
  },
};

/**
 * Six images: the grid shows four, the fourth says "+2", and the viewer shows all six
 */
export const LongGallery: Story = {
  args: { html: gallery(6) },
  play: async ({ canvasElement }) => {
    const page = within(canvasElement.ownerDocument.body);
    await userEvent.click(within(canvasElement).getByRole('link', { name: /^The beans ?, and 2 more images$/ }));
    const viewer = await page.findByRole('dialog', { name: 'Image 4 of 6' });
    await userEvent.keyboard('{End}');
    await expect(viewer).toHaveAccessibleName('Image 6 of 6');
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
  },
};

export const OneImage: Story = {
  args: { html: `<p>The first pumpkin:</p>${gallery(1)}` },
};

/**
 * A video link alone on its line becomes the player. Nothing loads from the provider before Play: the stories do not
 * press it.
 */
export const Video: Story = {
  args: {
    html: `<p>How to prune the tomatoes:</p>${videoBlock('youtube', 'https://youtu.be/v1', 'https://www.youtube-nocookie.com/embed/v1')}`,
  },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByRole('button', { name: 'Play the video: YouTube video' })).toBeInTheDocument();
    await expect(canvasElement.querySelector('iframe')).toBeNull();
    await expect(canvas.getByRole('link', { name: /Watch on YouTube/ })).toHaveAttribute('href', 'https://youtu.be/v1');
  },
};

/**
 * A block of a provider that the player does not know keeps its link
 */
export const VideoOfAnotherProvider: Story = {
  args: { html: videoBlock('other', 'https://example.com/video', 'https://example.com/embed') },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await waitFor(() => expect(canvas.getByRole('link', { name: 'https://example.com/video' })).toBeInTheDocument());
    await expect(canvas.queryByRole('button', { name: /Play the video/ })).toBeNull();
  },
};

/**
 * A long word or address wraps, and a long code block scrolls inside the content
 */
export const LongText: Story = {
  args: {
    html:
      '<p>The list of the seeds: https://example.com/seeds/tomatoes/heirloom/black-krim/2026/spring/the-full-list-of-the-community</p>' +
      '<pre><code>tomato-black-krim, tomato-green-zebra, bean-blue-lake, bean-borlotti, pumpkin-musquee-de-provence, lettuce-batavia</code></pre>',
  },
};

export const OnPhone: Story = {
  args: { html: `${TEXT}${gallery(3)}` },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
