import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import ErrorPage from './Error';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  usePage: () => ({ props: page.props }),
}));

vi.mock('@/Components/PageHeader', () => ({ default: () => null }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string) => `/${name}`,
    writable: true,
  });
});

describe('The error page', () => {
  it('leads a signed-in person to the dashboard (#1561)', () => {
    page.props = { auth: { user: { cuid: 'u1' } } };
    render(<ErrorPage status="403" />);

    expect(screen.getByRole('link', { name: 'Go back to the homepage' })).toHaveAttribute('href', '/dashboard');
  });

  it('leads a visitor to the homepage, also without the shared props', () => {
    page.props = { auth: { user: null } };
    const { unmount } = render(<ErrorPage status="404" />);
    expect(screen.getByRole('link', { name: 'Go back to the homepage' })).toHaveAttribute('href', '/');
    unmount();

    page.props = {};
    render(<ErrorPage status="404" />);
    expect(screen.getByRole('link', { name: 'Go back to the homepage' })).toHaveAttribute('href', '/');
  });
});
