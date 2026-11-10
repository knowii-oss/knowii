import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import LegalPage from './Show';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
}));
vi.mock('@/Components/PageHeader', () => ({ default: () => <header /> }));
vi.mock('@/Components/Footer', () => ({ default: () => <footer /> }));

describe('LegalPage', () => {
  it('shows the HTML rendered by the server and the date of the last change', () => {
    render(<LegalPage title="Imprint" updated="2026-11-10" draft={false} html="<h1>Imprint</h1><p>DeveloPassion SRL</p>" />);

    expect(screen.getByRole('heading', { level: 1, name: 'Imprint' })).toBeInTheDocument();
    expect(screen.getByText('DeveloPassion SRL')).toBeInTheDocument();
    expect(screen.getByText('2026-11-10')).toHaveAttribute('dateTime', '2026-11-10');
    expect(screen.queryByRole('note')).not.toBeInTheDocument();
  });

  it('says that a draft is not final', () => {
    render(<LegalPage title="Privacy policy" updated={null} draft={true} html="<h1>Privacy policy</h1>" />);

    expect(screen.getByRole('note')).toHaveTextContent('Draft: this text is not final yet.');
    expect(screen.queryByText(/Last updated/)).not.toBeInTheDocument();
  });
});
