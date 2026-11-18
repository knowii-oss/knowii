import type { ComponentProps } from 'react';
import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import type { TurnstileApi } from './Turnstile';
import { Turnstile } from './Turnstile';

const SITE_KEY = 'site-key-of-the-story';
const TOKEN = 'token-of-the-check';

type Check = 'passes' | 'asks' | 'fails';

/**
 * The options that the component gave to the stand-in for the last widget, its id, and the widgets that it removed. The
 * ids do not start again in each story: the widget of a story that goes away is removed after the next story starts.
 */
const widgets: { options: Parameters<TurnstileApi['render']>[1] | null; lastId: string | null; count: number; removed: string[] } = {
  options: null,
  lastId: null,
  count: 0,
  removed: [],
};

/**
 * A local stand-in of the Turnstile script: the story loads nothing from the network. With `window.turnstile` set, the
 * component does not add the script (`loadTurnstile`). The fake draws a box in the container, as the real widget does.
 */
function fakeTurnstile(check: Check): TurnstileApi {
  return {
    render: (container, options) => {
      widgets.count += 1;
      const widgetId = `widget-${widgets.count}`;
      widgets.options = options;
      widgets.lastId = widgetId;
      const box = container.ownerDocument.createElement('div');
      box.className = 'flex h-[65px] w-[300px] items-center gap-3 rounded-sm border border-border bg-surface px-3 text-sm';
      box.dataset['widgetId'] = widgetId;
      if (check === 'asks') {
        // The real widget asks some people to tick a box
        const label = container.ownerDocument.createElement('label');
        label.className = 'flex items-center gap-2';
        const checkbox = container.ownerDocument.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.addEventListener('change', () => options.callback(TOKEN));
        label.append(checkbox, 'Verify that you are a person');
        box.append(label);
      } else {
        box.append(check === 'passes' ? 'Success: you are a person (stand-in of the bot check)' : 'The check failed (stand-in)');
        queueMicrotask(() => (check === 'passes' ? options.callback(TOKEN) : options['error-callback']()));
      }
      container.append(box);
      return widgetId;
    },
    remove: (widgetId) => {
      widgets.removed.push(widgetId);
      document.querySelector(`[data-widget-id="${widgetId}"]`)?.remove();
    },
  };
}

interface StoryArgs extends ComponentProps<typeof Turnstile> {
  /**
   * What the stand-in of the check does
   */
  check: Check;
}

/**
 * The widget in a form, with the state of the token that the form keeps (it sends the token with the form)
 */
function TurnstileInAForm({ check: _check, onTokenChange, ...args }: StoryArgs) {
  const [token, setToken] = useState<string | null>(null);
  return (
    <div className="flex flex-col gap-2">
      <Turnstile
        {...args}
        onTokenChange={(next) => {
          setToken(next);
          onTokenChange(next);
        }}
      />
      <p role="status" className="text-sm text-muted">
        {token === null ? 'No token: the form cannot be sent yet.' : `Token: ${token}`}
      </p>
    </div>
  );
}

function noScriptOfCloudflare(document: Document): boolean {
  return document.querySelector('script[src*="challenges.cloudflare.com"]') === null;
}

/**
 * The bot check of the public forms (registration, newsletter, waiting list): Cloudflare Turnstile. The server checks the
 * token; the widget alone protects nothing. A token is good for one send: mount the widget again (a new `key`) for a
 * new token. Load it only when the person starts to use the form (#938).
 *
 * In these stories the script of Cloudflare is replaced with a local stand-in, so nothing loads from the network.
 *
 * Accessibility: the widget of Cloudflare holds its own controls and texts, in the theme of the page. The form must say
 * in text when it cannot be sent without the check.
 */
const meta = {
  component: Turnstile,
  render: (args) => <TurnstileInAForm {...args} />,
  args: {
    siteKey: SITE_KEY,
    onTokenChange: fn(),
    check: 'passes',
  },
  // The stand-in replaces the script for the story, and goes after it. A story that changes the theme gives it back.
  beforeEach: ({ args }) => {
    widgets.options = null;
    widgets.lastId = null;
    widgets.removed = [];
    window.turnstile = fakeTurnstile(args.check);
    const themeBefore = document.documentElement.getAttribute('data-theme');
    return () => {
      delete window.turnstile;
      if (themeBefore !== null) {
        document.documentElement.setAttribute('data-theme', themeBefore);
      }
    };
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The check passes without a question: the form gets a token. The widget gets the site key and the theme of the page.
 */
export const Passes: Story = {
  globals: { theme: 'dark' },
  play: async ({ args, canvasElement }) => {
    await waitFor(() => expect(args.onTokenChange).toHaveBeenCalledWith(TOKEN));
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent(`Token: ${TOKEN}`);
    await expect(widgets.options).toEqual(expect.objectContaining({ sitekey: SITE_KEY, theme: 'dark' }));
    await expect(noScriptOfCloudflare(canvasElement.ownerDocument)).toBe(true);
  },
};

export const InTheLightTheme: Story = {
  globals: { theme: 'light' },
  play: async () => {
    await waitFor(() => expect(widgets.options).toEqual(expect.objectContaining({ theme: 'light' })));
  },
};

/**
 * The check asks a question: no token until the person answers it
 */
export const AsksAQuestion: Story = {
  args: { check: 'asks' },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('status')).toHaveTextContent('No token');
    await userEvent.click(await canvas.findByRole('checkbox', { name: 'Verify that you are a person' }));
    await expect(args.onTokenChange).toHaveBeenCalledWith(TOKEN);
    await expect(canvas.getByRole('status')).toHaveTextContent(`Token: ${TOKEN}`);
  },
};

/**
 * The token expires (after some minutes without a send): the form has no token again
 */
export const TokenExpires: Story = {
  play: async ({ args, canvasElement }) => {
    await waitFor(() => expect(args.onTokenChange).toHaveBeenCalledWith(TOKEN));
    widgets.options?.['expired-callback']();
    await waitFor(() => expect(args.onTokenChange).toHaveBeenLastCalledWith(null));
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('No token');
  },
};

/**
 * The check fails (or the script cannot load): the form has no token
 */
export const Fails: Story = {
  args: { check: 'fails' },
  play: async ({ args, canvasElement }) => {
    await waitFor(() => expect(args.onTokenChange).toHaveBeenCalledWith(null));
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('No token');
  },
};

/**
 * A change of the theme draws the widget again in the new theme, and removes the old one
 */
export const FollowsTheTheme: Story = {
  globals: { theme: 'dark' },
  play: async ({ canvasElement }) => {
    await waitFor(() => expect(widgets.options).toEqual(expect.objectContaining({ theme: 'dark' })));
    const first = widgets.lastId;
    canvasElement.ownerDocument.documentElement.setAttribute('data-theme', 'light');
    await waitFor(() => expect(widgets.options).toEqual(expect.objectContaining({ theme: 'light' })));
    await expect(widgets.lastId).not.toBe(first);
    await expect(widgets.removed).toContain(first);
    await expect(canvasElement.querySelectorAll('[data-widget-id]')).toHaveLength(1);
  },
};
