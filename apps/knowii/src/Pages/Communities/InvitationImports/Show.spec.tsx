import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { InvitationImport } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { router } from '@inertiajs/react';
import InvitationImportShowPage from './Show';
import { importOf, pageOf, pageProps, rowOf } from './fixtures';
import { INVITATION_WORDS } from '@/Components/Communities/invitation-words';
import { emitSocketEvent } from '@/test-utils/fake-echo';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { visit: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
    writable: true,
  });
  Element.prototype.scrollIntoView = vi.fn();
});

const counts = (changes: Partial<InvitationImport['counts']>): InvitationImport['counts'] => ({
  rows: 0,
  valid: 0,
  refused: 0,
  queued: 0,
  sent: 0,
  held: 0,
  failed: 0,
  skipped: 0,
  still_invited: 0,
  not_sent: 0,
  ...changes,
});

const refusedRow = rowOf({
  cuid: 'r3',
  row_number: 3,
  email: 'not valid',
  outcome: 'refused',
  reason: 'invalid_email',
  reason_text: 'This is not a valid email address',
});
const validRow = rowOf({ cuid: 'r2', row_number: 2, access_groups: ['Paid'], member_tags: ['Founder'] });

beforeEach(() => {
  vi.restoreAllMocks();
  // A fixed day: the page compares the dates of "Invite again" with now. Only the date is fake, the timers are real
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date('2026-11-18T10:00:00Z'));
  vi.mocked(router.visit).mockClear();
  vi.spyOn(knowiiApiClient.communities.invitationImports, 'rows').mockImplementation((_community, _import, outcomes) =>
    Promise.resolve({
      ok: true,
      message: '',
      data: pageOf(
        outcomes.includes('refused')
          ? [refusedRow]
          : outcomes.includes('valid')
            ? [validRow]
            : outcomes.includes('sent')
              ? [rowOf({ outcome: 'sent', still_invited: true })]
              : [],
      ),
    }),
  );
});

afterEach(() => {
  vi.useRealTimers();
});

const renderPage = (invitationImport: InvitationImport, changes: Partial<typeof pageProps> = {}) =>
  render(
    <AppDataProvider>
      <InvitationImportShowPage {...pageProps} {...changes} invitationImport={invitationImport} />
    </AppDataProvider>,
  );

const axeViolations = async () =>
  (await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } })).violations;

describe('an import of invitations (#1390)', () => {
  it('shows a preview: the summary, the ignored columns, the refused rows first, then the rows to invite', async () => {
    renderPage(importOf({ counts: counts({ rows: 2, valid: 1, refused: 1 }), ignored_columns: ['name', 'phone'] }));

    expect(screen.getByText('1 row can be invited. 1 row is refused.')).toBeInTheDocument();
    expect(screen.getByText('Not used: name, phone')).toBeInTheDocument();
    const refused = await screen.findByRole('list', { name: /Refused rows/ });
    expect(within(refused).getByText('This is not a valid email address')).toBeInTheDocument();
    const valid = await screen.findByRole('list', { name: /Rows to invite/ });
    expect(within(valid).getByText(/Access groups: Paid/)).toBeInTheDocument();
    const headings = screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent);
    expect(headings[0]).toContain('Refused rows');
    expect(screen.getByRole('button', { name: 'Send 1 invitation' })).toBeInTheDocument();
    expect(await axeViolations()).toEqual([]);
  });

  it('shows no "Send" without a valid row, and to another manager', async () => {
    const { unmount } = renderPage(importOf({ counts: counts({ rows: 1, refused: 1 }) }));
    expect(screen.queryByRole('button', { name: /^Send/ })).not.toBeInTheDocument();
    expect(screen.getByText(INVITATION_WORDS.noValidRow)).toBeInTheDocument();
    unmount();

    renderPage(importOf({ counts: counts({ rows: 1, valid: 1 }) }), { userCuid: 'someone-else' });
    expect(screen.queryByRole('button', { name: /^Send/ })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: INVITATION_WORDS.discard })).toBeInTheDocument();
  });

  it('sends, then shows the counts in a polite status, and a live update loads the import again', async () => {
    const sending = importOf({
      state: 'sending',
      confirmed_at: '2026-11-18T10:00:00Z',
      counts: counts({ rows: 2, queued: 1, refused: 1 }),
    });
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'confirm').mockResolvedValue({ ok: true, message: '', data: sending });
    const get = vi.spyOn(knowiiApiClient.communities.invitationImports, 'get').mockResolvedValue({
      ok: true,
      message: '',
      data: { ...sending, counts: counts({ rows: 2, sent: 1, refused: 1, still_invited: 1 }) },
    });
    renderPage(importOf({ counts: counts({ rows: 2, valid: 1, refused: 1 }) }));

    await userEvent.click(screen.getByRole('button', { name: 'Send 1 invitation' }));

    const counted = await screen.findByText('2 rows: 1 waiting, 1 refused');
    expect(counted.closest('[role="status"]')).toHaveAttribute('aria-live', 'polite');
    // The button went away: the focus is on the summary
    await waitFor(() => expect(counted.closest('[role="status"]')?.parentElement).toHaveFocus());
    expect(screen.getByText(INVITATION_WORDS.sendingNote)).toBeInTheDocument();
    expect(screen.getByText('0 of 1 mails done')).toBeInTheDocument();

    act(() => emitSocketEvent('user.u1', 'invitation_imports.changed', { community_cuid: 'c1', import_cuid: 'i1' }));
    await waitFor(() => expect(get).toHaveBeenCalledWith('c1', 'i1'));
    expect(await screen.findByText('2 rows: 1 sent, 1 refused')).toBeInTheDocument();
    expect(await axeViolations()).toEqual([]);
  });

  it('keeps step 2 and says why when the send is refused', async () => {
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'confirm').mockResolvedValue({
      ok: false,
      status: 422,
      message: 'A community sends at most 1000 invitation emails in 24 hours. 3 more can go now.',
      errors: {},
    });
    renderPage(importOf({ counts: counts({ rows: 5, valid: 5 }) }));

    await userEvent.click(screen.getByRole('button', { name: 'Send 5 invitations' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('3 more can go now.');
    expect(screen.getByRole('list', { name: 'Progress' }).querySelector('[aria-current="step"]')).toHaveTextContent('Check');
  });

  it('asks before a discard, then goes back to the page of the imports', async () => {
    const remove = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'remove')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderPage(importOf({ counts: counts({ rows: 1, valid: 1 }) }));

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.discard }));
    const dialog = await screen.findByRole('dialog', { name: INVITATION_WORDS.discardQuestion });
    await userEvent.click(within(dialog).getByRole('button', { name: INVITATION_WORDS.discard }));

    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'i1'));
    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/invitation-imports.index/tests'));
  });

  it('a done import: the lists, "Send the mails that were not sent", and "Invite again" once its date came', async () => {
    const resend = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'resend')
      .mockResolvedValue({ ok: true, message: '', data: importOf({ state: 'sending' }) });
    renderPage(
      importOf({
        state: 'done',
        confirmed_at: '2026-11-14T10:00:00Z',
        finished_at: '2026-11-14T10:05:00Z',
        can_resend_not_joined_from: '2026-11-17T10:00:00Z',
        counts: counts({ rows: 3, sent: 1, held: 1, refused: 1, still_invited: 2, not_sent: 1 }),
      }),
    );

    expect(await screen.findByRole('list', { name: /^Sent/ })).toHaveTextContent('invitation pending');
    expect(screen.getByRole('button', { name: INVITATION_WORDS.deleteRecord })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.inviteAgain }));
    await waitFor(() => expect(resend).toHaveBeenCalledWith('c1', 'i1', 'not_joined'));
    expect(await axeViolations()).toEqual([]);
  });

  it('says from when the people can be invited again, and when they were', () => {
    const { unmount } = renderPage(
      importOf({
        state: 'done',
        confirmed_at: '2026-11-17T10:00:00Z',
        can_resend_not_joined_from: '2026-11-20T10:00:00Z',
        counts: counts({ rows: 1, sent: 1 }),
      }),
    );
    expect(screen.queryByRole('button', { name: INVITATION_WORDS.inviteAgain })).not.toBeInTheDocument();
    expect(screen.getByText(/You can invite the people who did not join again from/)).toBeInTheDocument();
    unmount();

    renderPage(
      importOf({
        state: 'done',
        confirmed_at: '2026-11-10T10:00:00Z',
        resent_at: '2026-11-14T10:00:00Z',
        counts: counts({ rows: 1, sent: 1 }),
      }),
      { permissions: { ...pageProps.permissions, canDeleteInvitationImports: false } },
    );
    expect(screen.getByText(/You invited the people who did not join again on/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: INVITATION_WORDS.deleteRecord })).not.toBeInTheDocument();
  });
  it('has the status region from the first render, with the summary of the preview in it', () => {
    renderPage(importOf({ counts: counts({ rows: 2, valid: 1, refused: 1 }) }));

    const status = screen.getByText('1 row can be invited. 1 row is refused.').closest('[role="status"]');
    expect(status).toHaveAttribute('aria-live', 'polite');
  });

  it('puts the focus on the summary after "Send the mails that were not sent"', async () => {
    const resend = vi.spyOn(knowiiApiClient.communities.invitationImports, 'resend').mockResolvedValue({
      ok: true,
      message: '',
      data: importOf({ state: 'sending', confirmed_at: '2026-11-17T10:00:00Z', counts: counts({ rows: 1, queued: 1 }) }),
    });
    renderPage(
      importOf({
        state: 'done',
        confirmed_at: '2026-11-17T10:00:00Z',
        finished_at: '2026-11-17T10:05:00Z',
        counts: counts({ rows: 1, held: 1, not_sent: 1 }),
      }),
    );

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.sendNotSent }));

    await waitFor(() => expect(resend).toHaveBeenCalledWith('c1', 'i1', 'not_sent'));
    const status = await screen.findByText('1 row: 1 waiting');
    await waitFor(() => expect(status.closest('[role="status"]')?.parentElement).toHaveFocus());
  });

  it('a live update loads the lists again only when the state of the import changed', async () => {
    const sending = importOf({ state: 'sending', confirmed_at: '2026-11-18T10:00:00Z', counts: counts({ rows: 2, queued: 2 }) });
    const get = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'get')
      .mockResolvedValueOnce({ ok: true, message: '', data: { ...sending, counts: counts({ rows: 2, queued: 1, sent: 1 }) } })
      .mockResolvedValueOnce({ ok: true, message: '', data: { ...sending, state: 'done', counts: counts({ rows: 2, sent: 2 }) } });
    const rows = vi.mocked(knowiiApiClient.communities.invitationImports.rows);
    renderPage(sending);
    await screen.findByRole('list', { name: /^Sent/ });
    await waitFor(() => expect(rows).toHaveBeenCalledTimes(3));

    act(() => emitSocketEvent('user.u1', 'invitation_imports.changed', { community_cuid: 'c1', import_cuid: 'i1' }));
    expect(await screen.findByText('2 rows: 1 waiting, 1 sent')).toBeInTheDocument();
    expect(rows).toHaveBeenCalledTimes(3);

    act(() => emitSocketEvent('user.u1', 'invitation_imports.changed', { community_cuid: 'c1', import_cuid: 'i1' }));
    expect(await screen.findByText('2 rows: 2 sent')).toBeInTheDocument();
    await waitFor(() => expect(rows).toHaveBeenCalledTimes(6));
    expect(get).toHaveBeenCalledTimes(2);
  });
});
