import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import EntitlementsCard from './EntitlementsCard';

describe('EntitlementsCard (#1386)', () => {
  it('lists the keys that the provider reported and the date of the last check', () => {
    render(<EntitlementsCard entitlements={{ keys: ['example.paid', 'example.plus'], checked_at: '2026-11-16T09:30:00+00:00' }} />);

    expect(screen.getByRole('heading', { name: 'What your account gives access to' })).toBeInTheDocument();
    const list = screen.getByRole('list', { name: 'What your account gives access to' });
    expect(
      within(list)
        .getAllByRole('listitem')
        .map((item) => item.textContent),
    ).toEqual(['example.paid', 'example.plus']);
    expect(screen.getByText(/Last checked on/)).toBeInTheDocument();
    expect(screen.getByText('November 16th, 2026')).toBeInTheDocument();
  });

  it('says so when there is nothing yet and the account was never checked', () => {
    render(<EntitlementsCard entitlements={{ keys: [], checked_at: null }} />);

    expect(screen.getByText('Nothing yet.')).toBeInTheDocument();
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
    expect(screen.getByText(/Not checked yet\./)).toBeInTheDocument();
  });
});
