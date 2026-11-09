import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CapturedArticle } from './CapturedArticle';

describe('CapturedArticle', () => {
  it('shows the article rendered by the server, with its reading time and source (#1247)', () => {
    render(
      <CapturedArticle
        article={{
          html: '<h1>Title</h1><p>Body <img src="/storage/community-resources/r/images/1.webp" alt="A chart"></p>',
          word_count: 1234,
          reading_time: 7,
          source: 'server',
          captured_at: new Date().toISOString(),
        }}
        excerpt="Not shown"
      />,
    );

    expect(screen.getByRole('heading', { name: 'The article' })).toBeInTheDocument();
    expect(screen.getByText(/7 min read · 1,234 words · captured by Knowii/)).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'A chart' })).toHaveAttribute('src', '/storage/community-resources/r/images/1.webp');
    expect(screen.queryByText('Not shown')).toBeNull();
  });

  it('shows the excerpt of the page without an article, and nothing without an excerpt', () => {
    const { unmount } = render(<CapturedArticle article={null} excerpt="The first lines of the page." />);
    expect(screen.getByRole('heading', { name: 'Excerpt' })).toBeInTheDocument();
    expect(screen.getByText('The first lines of the page.')).toBeInTheDocument();
    unmount();

    const { container } = render(<CapturedArticle article={null} excerpt={null} />);
    expect(container).toBeEmptyDOMElement();
  });
});
