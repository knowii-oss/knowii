import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import WelcomePage from './Welcome';

const page = vi.hoisted(() => ({ props: { auth: { user: null as { cuid: string } | null } } }));

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: page.props }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Components/PageHeader', () => ({ default: () => <header /> }));
vi.mock('@/Components/Footer', () => ({ default: () => <footer /> }));

const HEADING = "A place for your community's knowledge, ideas and inspiration";

describe('The home page (#1574)', () => {
  beforeEach(() => {
    page.props.auth.user = null;
  });

  it('a visitor: one heading, the two lines, "Sign up" and "Sign in", the document title', () => {
    render(<WelcomePage canLogin={true} canRegister={true} />);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1, name: HEADING })).toBeInTheDocument();
    expect(screen.getByText('Talk in discussion and chat spaces, and in direct messages.')).toBeInTheDocument();
    expect(screen.getByText('Share and keep resources and pages, and learn with courses.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign up' })).toHaveAttribute('href', '/register');
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('link', { name: 'Open your dashboard' })).not.toBeInTheDocument();
    expect(document.title).toBe('Home');
  });

  it('no waitlist, no "Coming soon", no emoji', () => {
    const { container } = render(<WelcomePage canLogin={true} canRegister={true} />);
    const text = container.textContent;

    expect(text).not.toContain('Coming soon');
    expect(text).not.toContain('revolutionary');
    expect(text.toLowerCase()).not.toContain('waitlist');
    expect(text).not.toMatch(/\p{Extended_Pictographic}/u);
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('a member: "Open your dashboard", no "Sign up" and no "Sign in"', () => {
    page.props.auth.user = { cuid: 'u1' };
    render(<WelcomePage canLogin={true} canRegister={true} />);

    expect(screen.getByRole('heading', { level: 1, name: HEADING })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Open your dashboard' })).toHaveAttribute('href', '/dashboard');
    expect(screen.queryByRole('link', { name: 'Sign up' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Sign in' })).not.toBeInTheDocument();
  });

  it('registration closed: no "Sign up", one line, and "Sign in"', () => {
    render(<WelcomePage canLogin={true} canRegister={false} />);

    expect(screen.getByText('Sign-up is closed for now.')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Sign up' })).not.toBeInTheDocument();
    expect(screen.queryByRole('form')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
  });

  it('a member does not read that sign-up is closed', () => {
    page.props.auth.user = { cuid: 'u1' };
    render(<WelcomePage canLogin={true} canRegister={false} />);

    expect(screen.queryByText('Sign-up is closed for now.')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Open your dashboard' })).toBeInTheDocument();
  });
});
