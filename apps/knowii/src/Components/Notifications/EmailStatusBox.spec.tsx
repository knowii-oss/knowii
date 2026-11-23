import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { EmailStatus } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { EmailStatusBox } from './EmailStatusBox';
import { EMAIL_STATUS_WORDS } from './email-status-words';

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const status = (changes: Partial<EmailStatus> = {}): EmailStatus => ({
  suppressed: true,
  reason: 'hard_bounce',
  since: '2026-11-23T10:00:00.000000Z',
  address_hint: 'ada@example.com',
  email_choices: [],
  ...changes,
});

const readsStatus = (value: EmailStatus) =>
  vi.spyOn(knowiiApiClient.notifications, 'emailStatus').mockResolvedValue({ ok: true, message: '', data: value });

describe('the box of a suppressed address (#1478)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows nothing while the address is not suppressed', async () => {
    const read = readsStatus(status({ suppressed: false, reason: null, since: null }));
    const { container } = render(<EmailStatusBox />);

    await vi.waitFor(() => expect(read).toHaveBeenCalled());
    expect(container).toBeEmptyDOMElement();
  });

  it('after a bounce: a region with a heading, the words of a bounce and the two buttons', async () => {
    readsStatus(status());
    render(<EmailStatusBox />);

    const region = await screen.findByRole('region', { name: 'We cannot reach ada@example.com.' });
    expect(region).toHaveTextContent(EMAIL_STATUS_WORDS.bounced);
    expect(screen.getByRole('heading', { level: 2, name: EMAIL_STATUS_WORDS.heading('ada@example.com') })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: EMAIL_STATUS_WORDS.tryAgain })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: EMAIL_STATUS_WORDS.changeAddress })).toHaveAttribute('href', '/profile.show');
  });

  it('after a complaint: the words of a complaint', async () => {
    readsStatus(status({ reason: 'complaint' }));
    render(<EmailStatusBox />);

    expect(await screen.findByText(EMAIL_STATUS_WORDS.complaint)).toBeInTheDocument();
    expect(screen.queryByText(EMAIL_STATUS_WORDS.bounced)).not.toBeInTheDocument();
  });

  it('"Try this address again" says so in the status region', async () => {
    readsStatus(status());
    const lift = vi
      .spyOn(knowiiApiClient.notifications, 'tryAddressAgain')
      .mockResolvedValue({ ok: true, message: '', data: status({ suppressed: false, reason: null, since: null }) });
    const user = userEvent.setup();
    render(<EmailStatusBox />);

    await user.click(await screen.findByRole('button', { name: EMAIL_STATUS_WORDS.tryAgain }));

    expect(lift).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('status')).toHaveTextContent(EMAIL_STATUS_WORDS.triedAgain);
  });

  it('a second try within the hour: an alert, and the focus stays on the button', async () => {
    readsStatus(status());
    vi.spyOn(knowiiApiClient.notifications, 'tryAddressAgain').mockResolvedValue({
      ok: false,
      status: 429,
      message: 'Too many',
      errors: {},
    });
    const user = userEvent.setup();
    render(<EmailStatusBox />);

    const button = await screen.findByRole('button', { name: EMAIL_STATUS_WORDS.tryAgain });
    await user.click(button);

    expect(await screen.findByRole('alert')).toHaveTextContent(EMAIL_STATUS_WORDS.tooSoon);
    expect(button).toHaveFocus();
  });

  it('has no automated violation (axe)', async () => {
    readsStatus(status());
    const { container } = render(<EmailStatusBox />);
    await screen.findByRole('region');

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
