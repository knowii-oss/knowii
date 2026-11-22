import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { InvitationImportRow } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { InvitationImportRowList } from './InvitationImportRowList';
import { pageOf, rowOf } from '@/Pages/Communities/InvitationImports/fixtures';

beforeAll(() => {
  // jsdom has no layout: the focus helper scrolls the row into view
  Element.prototype.scrollIntoView = vi.fn();
});

const rowsFrom = (first: number, count: number): InvitationImportRow[] =>
  Array.from({ length: count }, (_, index) =>
    rowOf({ cuid: `r${first + index}`, row_number: first + index, email: `p${first + index}@example.com`, outcome: 'sent' }),
  );

const page = (items: InvitationImportRow[], nextOffset: number | null, totalCount: number) => {
  const result = pageOf(items, nextOffset);
  return { ok: true as const, message: '', data: { ...result, pagination: { ...result.pagination, totalCount } } };
};

const renderList = (refreshKey = 0) =>
  render(<InvitationImportRowList communityCuid="c1" importCuid="i1" title="Sent" outcomes={['sent']} refreshKey={refreshKey} />);

beforeEach(() => {
  vi.restoreAllMocks();
});

describe('the rows of an import (#1390)', () => {
  it('"Load more" adds the next 50 rows, says how many came, and puts the focus on the first new row', async () => {
    const rows = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'rows')
      .mockResolvedValueOnce(page(rowsFrom(2, 50), 50, 60))
      .mockResolvedValueOnce(page(rowsFrom(52, 10), null, 60));
    renderList();

    const list = await screen.findByRole('list', { name: /^Sent/ });
    expect(within(list).getAllByRole('listitem')).toHaveLength(50);
    await userEvent.click(screen.getByRole('button', { name: 'Load more: Sent' }));

    await waitFor(() => expect(within(list).getAllByRole('listitem')).toHaveLength(60));
    expect(rows).toHaveBeenLastCalledWith('c1', 'i1', ['sent'], 50, 50);
    expect(screen.getByText('10 more loaded')).toHaveAttribute('role', 'status');
    await waitFor(() => expect(within(list).getByText('p52@example.com').closest('li')).toHaveFocus());
    expect(screen.queryByRole('button', { name: 'Load more: Sent' })).not.toBeInTheDocument();
    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations).toEqual([]);
  });

  it('a reload keeps the rows that "Load more" added, and the focus on its row', async () => {
    const rows = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'rows')
      .mockResolvedValueOnce(page(rowsFrom(2, 50), 50, 60))
      .mockResolvedValueOnce(page(rowsFrom(52, 10), null, 60))
      .mockResolvedValueOnce(page(rowsFrom(2, 60), null, 60));
    const { rerender } = renderList();
    const list = await screen.findByRole('list', { name: /^Sent/ });
    await userEvent.click(screen.getByRole('button', { name: 'Load more: Sent' }));
    const row = await within(list).findByText('p55@example.com');
    row.closest('li')?.focus();

    rerender(<InvitationImportRowList communityCuid="c1" importCuid="i1" title="Sent" outcomes={['sent']} refreshKey={1} />);

    await waitFor(() => expect(rows).toHaveBeenCalledTimes(3));
    expect(rows).toHaveBeenLastCalledWith('c1', 'i1', ['sent'], 60, 0);
    await waitFor(() => expect(within(list).getAllByRole('listitem')).toHaveLength(60));
    expect(within(list).getByText('p55@example.com').closest('li')).toHaveFocus();
  });

  it('a reload gives the focus to the name of the list when the focused row left it', async () => {
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'rows')
      .mockResolvedValueOnce(page(rowsFrom(2, 3), null, 3))
      .mockResolvedValueOnce(page(rowsFrom(3, 2), null, 2));
    const { rerender } = renderList();
    const list = await screen.findByRole('list', { name: /^Sent/ });
    within(list).getByText('p2@example.com').closest('li')?.focus();

    rerender(<InvitationImportRowList communityCuid="c1" importCuid="i1" title="Sent" outcomes={['sent']} refreshKey={1} />);

    await waitFor(() => expect(within(list).getAllByRole('listitem')).toHaveLength(2));
    await waitFor(() => expect(screen.getByRole('heading', { name: /^Sent/ })).toHaveFocus());
  });
});
