import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { EmptyState } from './EmptyState';
import { Button } from './Button';

describe('EmptyState', () => {
  it('shows the title, the description and the action, and hides the icon from screen readers', () => {
    const { container } = render(
      <EmptyState icon="💬" title="No posts yet" description="Posts will show here." action={<Button>Write a post</Button>} />,
    );
    expect(screen.getByText('No posts yet')).toBeInTheDocument();
    expect(screen.getByText('Posts will show here.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Write a post' })).toBeInTheDocument();
    expect(container.querySelector('[aria-hidden="true"]')).toHaveTextContent('💬');
  });

  it('renders without an icon, a description or an action', () => {
    const { container } = render(<EmptyState title="Nothing here" />);
    expect(screen.getByText('Nothing here')).toBeInTheDocument();
    expect(container.querySelector('[aria-hidden="true"]')).toBeNull();
  });
});
