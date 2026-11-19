import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { InstancePageProp } from '@knowii/common';
import ContactPage from './Contact';

const pageProps: { instance?: InstancePageProp } = {};

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  usePage: () => ({ props: pageProps }),
}));
vi.mock('@/Components/PageHeader', () => ({ default: () => <header /> }));
vi.mock('@/Components/Footer', () => ({ default: () => <footer /> }));

describe('ContactPage (#1653)', () => {
  beforeEach(() => {
    pageProps.instance = { contactEmail: 'hello@example.com', publisher: null, footerNote: null, footerLinks: [], socialLinks: [] };
  });

  it('shows the contact address of the instance as a mail link', () => {
    render(<ContactPage />);

    expect(screen.getByRole('heading', { level: 1, name: 'Contact' })).toBeInTheDocument();
    expect(screen.getByText(/You can contact us at the following address:/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'hello@example.com' })).toHaveAttribute('href', 'mailto:hello@example.com');
  });

  it('shows no address and no mail link without the values of the instance', () => {
    delete pageProps.instance;
    render(<ContactPage />);

    expect(screen.getByRole('heading', { level: 1, name: 'Contact' })).toBeInTheDocument();
    expect(screen.queryByText(/You can contact us/)).toBeNull();
    expect(screen.queryByRole('link')).toBeNull();
  });
});
