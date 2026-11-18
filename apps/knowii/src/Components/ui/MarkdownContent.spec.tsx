import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApplicationToolkitProvider } from '@/Components/ApplicationToolkit';
import { MarkdownContent } from './MarkdownContent';
import { ToolkitProvider } from './ToolkitContext';

const videoBlock = (provider: string, id: string, url: string, embedUrl: string) =>
  `<div class="video-embed not-prose" data-video-provider="${provider}" data-video-id="${id}" data-video-url="${url}" data-video-embed-url="${embedUrl}"><p><a href="${url}">${url}</a></p></div>`;

describe('MarkdownContent videos (#1206)', () => {
  it('turns a video block into the click-to-load player: nothing loads before Play', async () => {
    const url = 'https://youtu.be/dQw4w9WgXcQ';
    render(
      <MarkdownContent
        html={`<p>Watch this:</p>${videoBlock('youtube', 'dQw4w9WgXcQ', url, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')}`}
      />,
      { wrapper: ApplicationToolkitProvider },
    );

    const play = await screen.findByRole('button', { name: 'Play the video: YouTube video' });
    expect(document.querySelector('iframe')).toBeNull();
    expect(screen.getByRole('link', { name: /Watch on YouTube/ })).toHaveAttribute('href', url);
    expect(screen.queryByRole('link', { name: url })).not.toBeInTheDocument();

    await userEvent.click(play);

    const player = await screen.findByTitle('Video: YouTube video');
    expect(player).toHaveAttribute('src', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1');
  });

  it('keeps the link when a block misses an attribute or names another provider', async () => {
    render(<MarkdownContent html={videoBlock('dailymotion', 'x', 'https://example.com/v', 'https://example.com/e')} />, {
      wrapper: ApplicationToolkitProvider,
    });

    await waitFor(() => expect(screen.getByRole('link', { name: 'https://example.com/v' })).toBeInTheDocument());
    expect(screen.queryByRole('button', { name: /Play the video/ })).not.toBeInTheDocument();
  });

  it('builds the players again when the HTML changes', async () => {
    const { rerender } = render(
      <MarkdownContent
        html={videoBlock('vimeo', '76979871', 'https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1')}
      />,
      { wrapper: ApplicationToolkitProvider },
    );
    expect(await screen.findByRole('button', { name: 'Play the video: Vimeo video' })).toBeInTheDocument();

    rerender(<MarkdownContent html="<p>No video now</p>" />);

    expect(screen.getByText('No video now')).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByRole('button', { name: /Play the video/ })).not.toBeInTheDocument());
  });

  it('shows a plain link to the video without the application (the default of the toolkit, #657)', async () => {
    const url = 'https://youtu.be/dQw4w9WgXcQ';
    render(<MarkdownContent html={videoBlock('youtube', 'dQw4w9WgXcQ', url, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')} />);

    await waitFor(() => expect(screen.getByRole('link', { name: url })).toHaveAttribute('href', url));
    expect(screen.queryByRole('button', { name: /Play the video/ })).not.toBeInTheDocument();
  });

  it('renders the videos with the renderer of the provider, or of the property (#657)', async () => {
    const html = videoBlock('vimeo', '76979871', 'https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1');
    const fromProvider = (video: { id: string }, title: string) => <p>{`Provider: ${title} ${video.id}`}</p>;
    const fromProperty = (video: { id: string }, title: string) => <p>{`Property: ${title} ${video.id}`}</p>;
    const { rerender } = render(<MarkdownContent html={html} />, {
      wrapper: ({ children }) => <ToolkitProvider videoRenderer={fromProvider}>{children}</ToolkitProvider>,
    });
    expect(await screen.findByText('Provider: Vimeo video 76979871')).toBeInTheDocument();

    rerender(<MarkdownContent html={html} videoRenderer={fromProperty} />);

    expect(await screen.findByText('Property: Vimeo video 76979871')).toBeInTheDocument();
    expect(screen.queryByText(/^Provider:/)).not.toBeInTheDocument();
  });
});
