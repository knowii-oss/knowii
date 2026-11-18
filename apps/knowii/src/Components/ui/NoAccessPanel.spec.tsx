import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { Button } from './Button';
import { NoAccessPanel } from './NoAccessPanel';

describe('NoAccessPanel', () => {
  it('shows the heading as the h1, the reason and the next step, in a region named by the heading', () => {
    render(
      <NoAccessPanel title="You do not have access to this page" reason="This space is for the members of Atlas Garden.">
        <Button>Join</Button>
      </NoAccessPanel>,
    );

    const region = screen.getByRole('region', { name: 'You do not have access to this page' });
    expect(within(region).getByRole('heading', { level: 1, name: 'You do not have access to this page' })).toBeInTheDocument();
    expect(within(region).getByText('This space is for the members of Atlas Garden.')).toBeInTheDocument();
    expect(within(region).getByRole('button', { name: 'Join' })).toBeInTheDocument();
    expect(screen.getAllByRole('heading')).toHaveLength(1);
  });

  it('puts the next step after the reason', () => {
    render(
      <NoAccessPanel title="Title" reason="The reason">
        <a href="/somewhere">Next step</a>
      </NoAccessPanel>,
    );

    const reason = screen.getByText('The reason');
    const link = screen.getByRole('link', { name: 'Next step' });
    expect(reason.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('hides the icon from screen readers, and renders without an icon and without a next step', () => {
    const { container, rerender } = render(<NoAccessPanel icon="🔒" title="Title" reason="The reason" />);
    expect(container.querySelector('[aria-hidden="true"]')).toHaveTextContent('🔒');

    rerender(<NoAccessPanel title="Title" reason="The reason" />);
    expect(container.querySelector('[aria-hidden="true"]')).toBeNull();
    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.queryByRole('button')).toBeNull();
  });
});
