import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import MainMenu from './MainMenu';

vi.mock('@inertiajs/react', () => ({
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

const entries = [
  { label: 'Dashboard', href: '/dashboard', icon: <span /> },
  { label: 'Communities', href: '/communities', icon: <span />, match: 'prefix' as const },
];

describe('MainMenu', () => {
  it('marks the entry of the open page as the current page', () => {
    render(<MainMenu entries={entries} currentUrl="/dashboard" onNavigate={vi.fn()} />);

    expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Communities' })).not.toHaveAttribute('aria-current');
  });

  it('marks a prefix entry on a page below it', () => {
    render(<MainMenu entries={entries} currentUrl="/communities/knowii/about" onNavigate={vi.fn()} />);

    expect(screen.getByRole('link', { name: 'Communities' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute('aria-current');
  });

  it('marks no entry on another page', () => {
    render(<MainMenu entries={entries} currentUrl="/user/profile" onNavigate={vi.fn()} />);

    expect(screen.queryByRole('link', { current: 'page' })).toBeNull();
  });
});
