import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { NotificationEmailGroup } from '@knowii/common';
import { EMAIL_GROUP_NAMES, MAIL_STOP_WORDS } from '@/Components/Mail/mail-stop-words';
import MailStop from './Stop';

const shared = vi.hoisted(() => ({ visit: vi.fn(), title: '' }));

vi.mock('@inertiajs/react', () => ({
  router: { visit: shared.visit },
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, browserPageTitle }: { children: React.ReactNode; browserPageTitle: string }) => {
    shared.title = browserPageTitle;
    return (
      <div lang="en">
        <main>{children}</main>
      </div>
    );
  },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const urls = {
  stopUrl: 'https://knowii.test/mail/stop?group=events&user=u1&signature=s1',
  doneUrl: 'https://knowii.test/mail/stop?group=events&result=stopped&user=u1&signature=s2',
  undoUrl: 'https://knowii.test/mail/stop/undo?group=events&restore=default&user=u1&signature=s3',
  undoneUrl: 'https://knowii.test/mail/stop?group=events&result=undone&user=u1&signature=s4',
};

const renderPage = (state: 'confirm' | 'done' | 'undone' | 'invalid', group: NotificationEmailGroup | null = 'events') =>
  render(
    <MailStop
      state={state}
      group={group}
      stopUrl={state === 'confirm' ? urls.stopUrl : null}
      doneUrl={state === 'confirm' ? urls.doneUrl : null}
      undoUrl={state === 'done' ? urls.undoUrl : null}
      undoneUrl={state === 'done' ? urls.undoneUrl : null}
    />,
  );

describe('the stop page of a notification email (#1478)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    shared.visit.mockClear();
  });

  it('the words of each group, and "the weekly summary" in its own sentences', () => {
    expect(MAIL_STOP_WORDS.confirmTitle('replies_and_mentions')).toBe('Stop emails about replies and mentions?');
    expect(MAIL_STOP_WORDS.confirmButton('direct_messages')).toBe('Stop emails about direct messages');
    expect(MAIL_STOP_WORDS.doneTitle('important_news')).toBe('You will not get emails about important news any more.');
    expect(MAIL_STOP_WORDS.confirmTitle('weekly_summary')).toBe('Stop the weekly summary?');
    expect(MAIL_STOP_WORDS.confirmButton('weekly_summary')).toBe('Stop the weekly summary');
    expect(MAIL_STOP_WORDS.doneTitle('weekly_summary')).toBe('You will not get the weekly summary any more.');
    expect(MAIL_STOP_WORDS.undoneTitle('events')).toBe('Emails about events are on again.');
    expect(EMAIL_GROUP_NAMES.events).toBe('events');
  });

  it('confirm: one heading, the title of the browser, the text and one button; nothing is sent before the press', () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    renderPage('confirm');

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Stop emails about events?');
    expect(shared.title).toBe('Stop emails about events?');
    expect(screen.getByText(MAIL_STOP_WORDS.confirmText)).toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(1);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('the button sends the POST of the page to the signed address, then opens the done state', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({ type: 'opaqueredirect', status: 0 } as Response);
    const user = userEvent.setup();
    renderPage('confirm');

    await user.click(screen.getByRole('button', { name: 'Stop emails about events' }));

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    const [url, init] = fetchSpy.mock.calls[0] ?? [];
    expect(url).toBe(urls.stopUrl);
    expect(init?.method).toBe('POST');
    expect(String(init?.body)).toBe('source=page');
    expect(shared.visit).toHaveBeenCalledWith(urls.doneUrl);
  });

  it('a failed POST: an alert, and the focus stays on the button', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({ type: 'basic', status: 429 } as Response);
    const user = userEvent.setup();
    renderPage('confirm');

    const button = screen.getByRole('button', { name: 'Stop emails about events' });
    await user.click(button);

    expect(await screen.findByRole('alert')).toHaveTextContent(MAIL_STOP_WORDS.failed);
    expect(button).toHaveFocus();
    expect(shared.visit).not.toHaveBeenCalled();
  });

  it('done: what stopped and what still arrives, in text, with "Undo" and "Email settings"', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({ type: 'opaqueredirect', status: 0 } as Response);
    const user = userEvent.setup();
    renderPage('done');

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('You will not get emails about events any more.');
    expect(screen.getByText(MAIL_STOP_WORDS.doneEveryCommunity)).toBeInTheDocument();
    expect(screen.getByText(MAIL_STOP_WORDS.doneStillArrives)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: MAIL_STOP_WORDS.emailSettings })).toHaveAttribute('href', '/profile.notifications');

    await user.click(screen.getByRole('button', { name: MAIL_STOP_WORDS.undo }));
    expect(fetchSpy.mock.calls[0]?.[0]).toBe(urls.undoUrl);
    expect(shared.visit).toHaveBeenCalledWith(urls.undoneUrl);
  });

  it('undone: the group is on again', () => {
    renderPage('undone');

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Emails about events are on again.');
    expect(screen.getByText(MAIL_STOP_WORDS.undoneText)).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('not valid: the heading and the way to the settings, and no button', () => {
    renderPage('invalid', null);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(MAIL_STOP_WORDS.invalidTitle);
    expect(screen.getByText(MAIL_STOP_WORDS.invalidText)).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('works with the keyboard: Tab reaches the button, Enter presses it', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({ type: 'opaqueredirect', status: 0 } as Response);
    const user = userEvent.setup();
    renderPage('confirm');

    await user.tab();
    expect(screen.getByRole('button')).toHaveFocus();
    await user.keyboard('{Enter}');
    expect(fetchSpy).toHaveBeenCalledTimes(1);
  });

  it.each(['confirm', 'done', 'undone', 'invalid'] as const)('has no automated violation (axe): %s', async (state) => {
    const { container } = renderPage(state, state === 'invalid' ? null : 'events');

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
