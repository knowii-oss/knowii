import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { router } from '@inertiajs/react';
import InvitationImportsIndexPage from './Index';
import { importOf, pageOf, pageProps } from './fixtures';
import { INVITATION_WORDS } from '@/Components/Communities/invitation-words';

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

beforeEach(() => {
  vi.restoreAllMocks();
  vi.mocked(router.visit).mockClear();
});

const renderPage = () =>
  render(
    <AppDataProvider>
      <InvitationImportsIndexPage {...pageProps} />
    </AppDataProvider>,
  );

const axeViolations = async () =>
  (await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } })).violations;

describe('the page "Invite from a file" (#1390)', () => {
  it('shows the form, the help, the template and the empty list', async () => {
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'list').mockResolvedValue({ ok: true, message: '', data: pageOf([]) });
    renderPage();

    expect(screen.getByRole('heading', { level: 1, name: INVITATION_WORDS.importTitle })).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Progress' })).toBeInTheDocument();
    const file = screen.getByLabelText(INVITATION_WORDS.fileLabel);
    expect(file).toHaveAttribute('accept', '.csv,text/csv');
    expect(file).toHaveAccessibleDescription(expect.stringContaining('The column email is required'));
    expect(screen.getByRole('link', { name: INVITATION_WORDS.downloadTemplate })).toHaveAttribute('href', '/templates/invitations.csv');
    expect(await screen.findByText(INVITATION_WORDS.noImport)).toBeInTheDocument();
    expect(await axeViolations()).toEqual([]);
  });

  it('asks for a file, sends the file with the defaults, and opens the import', async () => {
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'list').mockResolvedValue({ ok: true, message: '', data: pageOf([]) });
    const upload = vi
      .spyOn(knowiiApiClient.communities.invitationImports, 'upload')
      .mockResolvedValue({ ok: true, message: '', data: importOf({ cuid: 'new' }) });
    renderPage();

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.checkFile }));
    expect(screen.getByRole('alert')).toHaveTextContent(INVITATION_WORDS.chooseFile);
    expect(upload).not.toHaveBeenCalled();

    const file = new File(['email\ngrace@example.com\n'], 'people.csv', { type: 'text/csv' });
    await userEvent.upload(screen.getByLabelText(INVITATION_WORDS.fileLabel), file);
    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButton }));
    await userEvent.click(within(await screen.findByRole('dialog')).getByRole('checkbox', { name: 'Paid' }));
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: INVITATION_WORDS.pickerDone }));
    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.checkFile }));

    await waitFor(() =>
      expect(upload).toHaveBeenCalledWith('c1', { file, default_role: 'member', access_groups: ['g1'], member_tags: [] }),
    );
    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/invitation-imports.show/tests/new'));
  });

  it('shows an error of the file under the field, as an alert tied to it', async () => {
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'list').mockResolvedValue({ ok: true, message: '', data: pageOf([]) });
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'upload').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { file: ['The file needs a column named email'] },
    });
    renderPage();

    await userEvent.upload(screen.getByLabelText(INVITATION_WORDS.fileLabel), new File(['name\n'], 'people.csv', { type: 'text/csv' }));
    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.checkFile }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('The file needs a column named email');
    expect(screen.getByLabelText(INVITATION_WORDS.fileLabel)).toHaveAccessibleDescription(
      expect.stringContaining('The file needs a column named email'),
    );
    expect(screen.getByLabelText(INVITATION_WORDS.fileLabel)).toHaveFocus();
    expect(router.visit).not.toHaveBeenCalled();
    expect(await axeViolations()).toEqual([]);
  });

  it('lists the earlier imports in words, each a link, and loads more', async () => {
    const first = importOf({
      cuid: 'a',
      state: 'done',
      counts: { rows: 12, valid: 0, refused: 2, queued: 0, sent: 10, held: 0, failed: 0, skipped: 0, still_invited: 3, not_sent: 0 },
    });
    vi.spyOn(knowiiApiClient.communities.invitationImports, 'list')
      .mockResolvedValueOnce({ ok: true, message: '', data: pageOf([first], 20) })
      .mockResolvedValueOnce({ ok: true, message: '', data: pageOf([importOf({ cuid: 'b', started_by: null })]) });
    renderPage();

    const list = await screen.findByRole('list', { name: INVITATION_WORDS.earlierImports });
    const link = within(list).getByRole('link');
    expect(link).toHaveAttribute('href', '/invitation-imports.show/tests/a');
    expect(link).toHaveTextContent('Ada Example, Done, 12 rows: 10 sent, 2 refused');

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.loadMore }));
    expect(await screen.findByText('1 more loaded')).toBeInTheDocument();
    expect(within(list).getAllByRole('link')[1]).toHaveTextContent('Deleted member, Not sent yet');
    await waitFor(() => expect(within(list).getAllByRole('link')[1]).toHaveFocus());
    expect(await axeViolations()).toEqual([]);
  });
});
