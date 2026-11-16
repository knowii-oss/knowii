import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { knowiiApiClient } from '@knowii/common';
import { resetTimeZoneAsk, TimeZoneAsk } from './TimeZoneAsk';

const shared = vi.hoisted(() => ({ props: {} as Record<string, unknown>, reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  router: { reload: shared.reload },
  usePage: () => ({ props: shared.props, url: '/' }),
}));

const asUser = (timeZone: string | null | undefined) => {
  shared.props = { auth: { user: { cuid: 'u1', name: 'Ada', time_zone: timeZone } } };
};

// The real formatter, kept before any spy replaces it
const real = Intl.DateTimeFormat;

function browserIn(zone: string | null) {
  // A function, not an arrow: the code calls it with and without `new`
  vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(function (...args: ConstructorParameters<typeof Intl.DateTimeFormat>) {
    const format = new real(...args);
    if (args[0] === undefined) {
      return { resolvedOptions: () => ({ ...format.resolvedOptions(), timeZone: zone ?? '' }) } as unknown as Intl.DateTimeFormat;
    }
    return format;
  } as unknown as typeof Intl.DateTimeFormat);
}

describe('the time zone of the account and of the browser (#1476, design 6.7 and 9.9)', () => {
  let save: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    resetTimeZoneAsk();
    window.localStorage.clear();
    shared.reload.mockClear();
    save = vi
      .spyOn(knowiiApiClient.users, 'setTimeZone')
      .mockImplementation((zone) => Promise.resolve({ ok: true, message: '', data: { time_zone: zone } }));
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('an account with no zone', () => {
    it('saves the zone of the browser one time, without a question', async () => {
      browserIn('Europe/Paris');
      asUser(null);
      const { rerender } = render(<TimeZoneAsk />);

      await waitFor(() => expect(save).toHaveBeenCalledWith('Europe/Paris'));
      expect(screen.queryByRole('region')).toBeNull();
      expect(screen.queryByRole('dialog')).toBeNull();
      await waitFor(() => expect(shared.reload).toHaveBeenCalledWith({ only: ['auth'] }));

      // A second render of the layout does not write again
      rerender(<TimeZoneAsk />);
      expect(save).toHaveBeenCalledTimes(1);
    });

    it('writes nothing when the browser gives no zone', () => {
      browserIn(null);
      asUser(null);
      render(<TimeZoneAsk />);

      expect(save).not.toHaveBeenCalled();
    });

    it('writes nothing for an old session without the field (undefined is not "not known")', () => {
      browserIn('Europe/Paris');
      asUser(undefined);
      render(<TimeZoneAsk />);

      expect(save).not.toHaveBeenCalled();
    });
  });

  describe('an account with a zone', () => {
    it('never replaces a zone that exists without a click', () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      render(<TimeZoneAsk />);

      expect(save).not.toHaveBeenCalled();
    });

    it('shows nothing when the browser reports the same zone', () => {
      browserIn('Europe/Brussels');
      asUser('Europe/Brussels');
      const { container } = render(<TimeZoneAsk />);

      expect(container).toBeEmptyDOMElement();
    });

    it('asks "Show times in <zone>?" in a plain region: not a dialog, and it never takes the focus', () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      render(<TimeZoneAsk />);

      const region = screen.getByRole('region', { name: 'Show times in Europe/Paris?' });
      expect(region).toBeInTheDocument();
      expect(screen.queryByRole('dialog')).toBeNull();
      expect(screen.getByRole('button', { name: 'Yes, use Europe/Paris' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Keep Europe/Brussels' })).toBeInTheDocument();
      expect(document.body).toHaveFocus();
    });

    it('"Yes, use" saves the new zone, loads the account again and remembers the answer for the pair', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      const user = userEvent.setup();
      const { unmount } = render(<TimeZoneAsk />);

      await user.click(screen.getByRole('button', { name: 'Yes, use Europe/Paris' }));

      expect(save).toHaveBeenCalledWith('Europe/Paris');
      await waitFor(() => expect(screen.queryByRole('region')).toBeNull());
      expect(shared.reload).toHaveBeenCalledWith({ only: ['auth'] });
      unmount();

      // The answer stays for that pair of zones, also on the next page
      render(<TimeZoneAsk />);
      expect(screen.queryByRole('region')).toBeNull();
    });

    it('"Keep" changes nothing, and the question does not come back for that pair', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      const user = userEvent.setup();
      const { unmount } = render(<TimeZoneAsk />);

      await user.click(screen.getByRole('button', { name: 'Keep Europe/Brussels' }));

      expect(save).not.toHaveBeenCalled();
      expect(screen.queryByRole('region')).toBeNull();
      unmount();
      render(<TimeZoneAsk />);
      expect(screen.queryByRole('region')).toBeNull();
    });

    it('asks again for a new zone, and the answer is kept per account', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      const user = userEvent.setup();
      const first = render(<TimeZoneAsk />);
      await user.click(screen.getByRole('button', { name: 'Keep Europe/Brussels' }));
      first.unmount();

      // Another account on the same browser
      shared.props = { auth: { user: { cuid: 'u2', name: 'Ben', time_zone: 'Europe/Brussels' } } };
      const second = render(<TimeZoneAsk />);
      expect(screen.getByRole('region')).toBeInTheDocument();
      second.unmount();

      // The same account, in another zone
      browserIn('Asia/Tokyo');
      asUser('Europe/Brussels');
      render(<TimeZoneAsk />);
      expect(screen.getByRole('region', { name: 'Show times in Asia/Tokyo?' })).toBeInTheDocument();
    });

    it('keeps the question and says "Not saved. Retry." when the save fails', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      save.mockResolvedValue({ ok: false, status: 422, message: 'Failed', errors: {} });
      const user = userEvent.setup();
      render(<TimeZoneAsk />);

      await user.click(screen.getByRole('button', { name: 'Yes, use Europe/Paris' }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
      expect(screen.getByRole('region')).toBeInTheDocument();
      expect(shared.reload).not.toHaveBeenCalled();
      expect(screen.getByRole('button', { name: 'Yes, use Europe/Paris' })).not.toBeDisabled();
    });

    it('asks again at the next page when the storage of the browser is not available', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('full');
      });
      vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new Error('denied');
      });
      const user = userEvent.setup();
      const { unmount } = render(<TimeZoneAsk />);

      await user.click(screen.getByRole('button', { name: 'Keep Europe/Brussels' }));
      expect(screen.queryByRole('region')).toBeNull();
      unmount();

      render(<TimeZoneAsk />);
      expect(screen.getByRole('region')).toBeInTheDocument();
    });

    it('has no automated violation (axe)', async () => {
      browserIn('Europe/Paris');
      asUser('Europe/Brussels');
      const { container } = render(<TimeZoneAsk />);

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  });

  it('shows nothing and writes nothing for a visitor', () => {
    browserIn('Europe/Paris');
    shared.props = { auth: { user: null } };
    const { container } = render(<TimeZoneAsk />);

    expect(container).toBeEmptyDOMElement();
    expect(save).not.toHaveBeenCalled();
  });
});
