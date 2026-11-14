import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MarkdownContent } from './MarkdownContent';

const item = (name: string, extra = '') =>
  `<a class="image-gallery-item" href="/storage/uploads/${name}.webp"><img src="/storage/uploads/${name}.webp" alt="The ${name}" loading="lazy" decoding="async" />${extra}</a>`;

const gallery = (names: string[]) =>
  `<div class="image-gallery not-prose" data-image-count="${names.length}">${names
    .map((name, index) =>
      item(
        name,
        names.length > 4 && index === 3
          ? `<span class="image-gallery-more" aria-hidden="true">+${names.length - 4}</span><span class="image-gallery-more-label">, and ${names.length - 4} more images</span>`
          : '',
      ),
    )
    .join('')}</div>`;

function Harness({ html }: { html: string }) {
  const [shown, setShown] = useState(true);
  return (
    <>
      <button type="button" onClick={() => setShown(false)}>
        Hide
      </button>
      {shown && <MarkdownContent html={html} />}
    </>
  );
}

describe('MarkdownContent galleries (#1207, ADR 0039)', () => {
  it('opens the image in the viewer with its caption and its position, and moves with the keyboard', async () => {
    render(<Harness html={`<p>Our trip:</p>${gallery(['harbour', 'market', 'beach'])}`} />);

    await userEvent.click(screen.getByRole('link', { name: 'The market' }));

    const viewer = await screen.findByRole('dialog');
    expect(viewer).toHaveAccessibleName('Image 2 of 3');
    // The small image of the grid has the same name: look in the viewer
    expect(within(viewer).getByRole('img', { name: 'The market' })).toHaveAttribute('src', '/storage/uploads/market.webp');
    expect(screen.getByRole('link', { name: /Open the original/ })).toHaveAttribute('href', '/storage/uploads/market.webp');
    expect(screen.getByRole('link', { name: /Open the original/ })).toHaveAttribute('target', '_blank');
    await waitFor(() => expect(screen.getByRole('button', { name: 'Close the viewer' })).toHaveFocus());

    await userEvent.keyboard('{ArrowRight}');
    expect(viewer).toHaveAccessibleName('Image 3 of 3');
    // After the last image comes the first one
    await userEvent.keyboard('{ArrowRight}');
    expect(viewer).toHaveAccessibleName('Image 1 of 3');
    await userEvent.keyboard('{End}');
    expect(viewer).toHaveAccessibleName('Image 3 of 3');
    await userEvent.keyboard('{Home}');
    expect(viewer).toHaveAccessibleName('Image 1 of 3');

    await userEvent.click(screen.getByRole('button', { name: 'Previous image' }));
    expect(viewer).toHaveAccessibleName('Image 3 of 3');
    await userEvent.click(screen.getByRole('button', { name: 'Next image' }));
    expect(viewer).toHaveAccessibleName('Image 1 of 3');

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('shows every image of a long gallery in the viewer, and moves with a swipe', async () => {
    render(<Harness html={gallery(['one', 'two', 'three', 'four', 'five', 'six'])} />);

    // The fourth image says that there are more
    await userEvent.click(screen.getByRole('link', { name: /^The four ?, and 2 more images$/ }));

    const viewer = await screen.findByRole('dialog');
    expect(viewer).toHaveAccessibleName('Image 4 of 6');

    const image = within(viewer).getByRole('img', { name: 'The four' });
    const area = image.parentElement?.parentElement as HTMLElement;
    fireEvent.pointerDown(area, { clientX: 300 });
    fireEvent.pointerUp(area, { clientX: 180 });
    expect(viewer).toHaveAccessibleName('Image 5 of 6');

    fireEvent.pointerDown(area, { clientX: 100 });
    fireEvent.pointerUp(area, { clientX: 260 });
    expect(viewer).toHaveAccessibleName('Image 4 of 6');

    // A small move is not a swipe
    fireEvent.pointerDown(area, { clientX: 100 });
    fireEvent.pointerUp(area, { clientX: 120 });
    expect(viewer).toHaveAccessibleName('Image 4 of 6');
  });

  it('has no previous and next for one image, and leaves a click with a modifier key to the browser', async () => {
    render(<Harness html={gallery(['alone'])} />);
    const link = screen.getByRole('link', { name: 'The alone' });

    // Ctrl + click opens the file in a new tab: the viewer stays closed
    expect(fireEvent.click(link, { ctrlKey: true })).toBe(true);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    await userEvent.click(link);
    expect(await screen.findByRole('dialog')).toHaveAccessibleName('Image 1 of 1');
    expect(screen.queryByRole('button', { name: 'Next image' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Previous image' })).not.toBeInTheDocument();
  });

  it('leaves an image in a sentence and a normal link alone', async () => {
    render(<Harness html={'<p>Look at <img src="/a.webp" alt="This" /> and <a href="https://example.com">a link</a>.</p>'} />);

    expect(fireEvent.click(screen.getByRole('img', { name: 'This' }))).toBe(true);
    expect(fireEvent.click(screen.getByRole('link', { name: 'a link' }))).toBe(true);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
