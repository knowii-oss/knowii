import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import ErrorPage from './Error';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const layout = vi.hoisted(() => ({ title: '' }));

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: page.props }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, browserPageTitle }: { children: React.ReactNode; browserPageTitle: string }) => {
    layout.title = browserPageTitle;
    return <div data-testid="app-layout">{children}</div>;
  },
}));
vi.mock('@/Components/PageHeader', () => ({ default: () => <header data-testid="guest-header" /> }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string) => `/${name}`,
    writable: true,
  });
});

const CASES = [
  { status: 403, title: 'No access', reason: 'You do not have access to this page.' },
  { status: 404, title: 'Page not found', reason: 'This page does not exist, or it moved.' },
  { status: 500, title: 'Server error', reason: 'Something went wrong on our side. Try again in a minute.' },
  { status: 503, title: 'Under maintenance', reason: 'Knowii is under maintenance. Come back in a few minutes.' },
];

describe('The error page (#1574)', () => {
  beforeEach(() => {
    layout.title = '';
  });

  describe.each(CASES)('$status', ({ status, title, reason }) => {
    it('a signed-in person: the normal layout, the case in words, one line, and "Go to my communities"', () => {
      page.props = { auth: { user: { cuid: 'u1' } } };
      const { container } = render(<ErrorPage status={status} />);

      expect(screen.getByTestId('app-layout')).toBeInTheDocument();
      expect(screen.queryByTestId('guest-header')).not.toBeInTheDocument();
      expect(layout.title).toBe(title);
      expect(screen.getByRole('heading', { level: 1, name: title })).toBeInTheDocument();
      expect(screen.getByText(reason)).toBeInTheDocument();
      expect(screen.getAllByRole('link')).toHaveLength(1);
      expect(screen.getByRole('link', { name: 'Go to my communities' })).toHaveAttribute('href', '/dashboard');
      expect(container.textContent).not.toContain(String(status));
      expect(container.textContent).not.toMatch(/\p{Extended_Pictographic}/u);
    });

    it('a visitor: the layout of the public pages, the document title, and "Go to the home page"', () => {
      page.props = { auth: { user: null } };
      const { container } = render(<ErrorPage status={status} />);

      expect(screen.queryByTestId('app-layout')).not.toBeInTheDocument();
      expect(screen.getByTestId('guest-header')).toBeInTheDocument();
      expect(document.title).toBe(title);
      expect(screen.getByRole('heading', { level: 1, name: title })).toBeInTheDocument();
      expect(screen.getByText(reason)).toBeInTheDocument();
      expect(screen.getAllByRole('link')).toHaveLength(1);
      expect(screen.getByRole('link', { name: 'Go to the home page' })).toHaveAttribute('href', '/');
      expect(container.textContent).not.toContain(String(status));
    });
  });

  it('without the shared props (the request never reached the web middleware): the visitor version', () => {
    page.props = {};
    render(<ErrorPage status={503} />);

    expect(screen.getByTestId('guest-header')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to the home page' })).toHaveAttribute('href', '/');
  });

  it('another status gets the texts of a server error', () => {
    page.props = { auth: { user: null } };
    render(<ErrorPage status={502} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Server error' })).toBeInTheDocument();
  });
});
