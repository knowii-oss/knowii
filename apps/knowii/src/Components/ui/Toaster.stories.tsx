import type { MouseEvent } from 'react';
import { useEffect, useRef } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, waitFor, within } from 'storybook/test';
import type { ToastMessage } from '@knowii/common';
import { AppDataProvider, MAX_VISIBLE_TOASTS, useAppData } from '@knowii/common';
import { Button } from './Button';
import { Toaster } from './Toaster';

// Outside the application, there is no page to visit: a click on the link of a toast stops the visit (the link is a plain
// <a> without a ToolkitProvider; Inertia does not visit either when the default action is prevented, see the Breadcrumb
// stories). The toast still goes.
function stopVisit(event: MouseEvent): void {
  event.preventDefault();
}

const SPACE_CREATED: ToastMessage = {
  severity: 'success',
  summary: 'Space created',
  detail: 'Vegetables is in the navigation of Atlas Garden.',
  action: { label: 'Open the space', href: '/communities/atlas-garden/vegetables' },
};

// The kinds of toast. They stay (sticky) so that they can be seen: in the application, a toast without a link goes away
// after its life (DEFAULT_TOAST_LIFE), and the pointer or the focus on it pauses the timer (Display.spec.tsx tests it)
const POST_PUBLISHED: ToastMessage = {
  severity: 'success',
  summary: 'Post published',
  detail: 'The members of Vegetables can read it now.',
  sticky: true,
};
const SEED_SWAP: ToastMessage = { severity: 'info', summary: 'The seed swap starts on Saturday', sticky: true };
const SESSION_ENDS: ToastMessage = {
  severity: 'warn',
  summary: 'Your session ends in 5 minutes',
  detail: 'Save your text to keep it.',
  sticky: true,
};
const NOT_SAVED: ToastMessage = {
  severity: 'error',
  summary: 'The post was not saved',
  detail: 'Check your connection and try again.',
  sticky: true,
};

interface StoryArgs {
  /**
   * The toasts that show when the story starts (showToast of the AppDataProvider)
   */
  toasts: readonly ToastMessage[];
}

/**
 * Shows the toasts of the story once, when it starts
 */
function ShowToasts({ toasts }: StoryArgs) {
  const { showToast } = useAppData();
  const shown = useRef(false);
  useEffect(() => {
    if (!shown.current && toasts.length > 0) {
      shown.current = true;
      showToast([...toasts]);
    }
  }, [showToast, toasts]);
  return null;
}

/**
 * A button of the page that shows a toast, as "Create the space" does
 */
function CreateSpaceButton() {
  const { showToast } = useAppData();
  return <Button onClick={() => showToast(SPACE_CREATED)}>Create the space</Button>;
}

/**
 * The toaster as the application mounts it: once, inside the AppDataProvider, before the page
 */
function ToasterInTheApplication({ toasts }: StoryArgs) {
  return (
    <AppDataProvider>
      <div onClickCapture={stopVisit}>
        <Toaster />
        <ShowToasts toasts={toasts} />
        <div className="flex flex-col items-start gap-2 pt-48">
          <p className="text-sm text-muted">The page</p>
          <CreateSpaceButton />
        </div>
      </div>
    </AppDataProvider>
  );
}

function region(canvasElement: HTMLElement): HTMLElement {
  return within(canvasElement).getByRole('region', { name: 'Notifications' });
}

/**
 * The short messages after an action ("Post published"), at the top of the screen. Call `showToast()` of `useAppData()`;
 * the `Toaster` is mounted once in `main.tsx`, so a toast stays during an Inertia visit. A toast goes away after its life;
 * a toast with `sticky` or with a link (`action`) stays until the member closes it or follows the link (WCAG 2.2.1). At
 * most five show at once. Do not use a toast for an error that the member must fix in a form: show it at the field.
 *
 * Accessibility: a region named "Notifications" with a list of the toasts. Each toast is its own live region: a status,
 * or an alert for an error (inside its list item, so the list stays a list). The region is in the page before its text,
 * so a screen reader reads every new toast, once. A new toast never takes the focus. The pointer
 * or the focus on a toast pauses its timer. When a toast that holds the focus goes away, the focus goes back to the
 * element that had it before, else to another toast. The icons and the colored bar are hidden from screen readers.
 */
const meta = {
  component: Toaster,
  render: (args) => <ToasterInTheApplication {...args} />,
  args: {
    toasts: [POST_PUBLISHED, SEED_SWAP, SESSION_ENDS],
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Severities: Story = {
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    // The list is not a live region: each toast is one, so that no toast is read twice
    await expect(notifications.querySelector('ol')).not.toHaveAttribute('aria-live');
    await waitFor(() => expect(within(notifications).getAllByRole('listitem')).toHaveLength(3));
    await expect(within(notifications).getAllByRole('button', { name: 'Dismiss notification' })).toHaveLength(3);
    await expect(within(notifications).queryByRole('alert')).toBeNull();
    // The text of each toast goes into its status, once the status is in the page
    await waitFor(() => expect(within(notifications).getAllByRole('status')).toHaveLength(3));
    await waitFor(() => expect(within(notifications).getAllByRole('status')[0]).toHaveTextContent('Post published'));
  },
};

/**
 * An error toast is an alert: a screen reader reads it at once. The alert is inside its item of the list, so the list
 * keeps its items, and the toast stays for the check of the page
 */
export const ErrorToast: Story = {
  args: { toasts: [SESSION_ENDS, NOT_SAVED] },
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    const alert = await within(notifications).findByRole('alert');
    await waitFor(() => expect(alert).toHaveTextContent('The post was not saved'));
    await expect(alert.closest('li')).not.toHaveAttribute('role');
    await expect(within(notifications).getAllByRole('listitem')).toHaveLength(2);
    // The other toast is a status: each toast is read once, by its own region
    await waitFor(() => expect(within(notifications).getByRole('status')).toHaveTextContent('Your session ends in 5 minutes'));
  },
};

/**
 * "Dismiss notification" closes its toast, and the other one stays
 */
export const DismissAnError: Story = {
  args: { toasts: [SESSION_ENDS, NOT_SAVED] },
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    const alert = await within(notifications).findByRole('alert');
    await waitFor(() => expect(alert).toHaveTextContent('The post was not saved'));
    const item = alert.closest('li');
    await expect(item).not.toHaveAttribute('role');
    await expect(within(notifications).getAllByRole('listitem')).toHaveLength(2);

    if (item) {
      await userEvent.click(within(item).getByRole('button', { name: 'Dismiss notification' }));
    }
    await waitFor(() => expect(within(notifications).queryByRole('alert')).not.toBeInTheDocument());
    await expect(within(notifications).getAllByRole('listitem')).toHaveLength(1);
    await expect(within(notifications).getByText('Your session ends in 5 minutes')).toBeInTheDocument();
  },
};

/**
 * A toast with a link to the next step stays until it is closed or its link is followed. A click on the link closes it.
 */
export const WithALink: Story = {
  args: { toasts: [SPACE_CREATED] },
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    const link = await within(notifications).findByRole('link', { name: 'Open the space' });
    await expect(link).toHaveAttribute('href', '/communities/atlas-garden/vegetables');

    const address = canvasElement.ownerDocument.location.href;
    await userEvent.click(link);
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
    await waitFor(() => expect(within(notifications).queryByText('Space created')).not.toBeInTheDocument());
  },
};

/**
 * The keyboard path: a new toast does not take the focus. Shift+Tab from the page reaches the toast (the toaster is
 * before the page), Enter on "Dismiss notification" closes it, and the focus goes back to the button of the page.
 */
export const KeyboardPath: Story = {
  args: { toasts: [] },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const notifications = region(canvasElement);
    const button = canvas.getByRole('button', { name: 'Create the space' });

    await step('A new toast does not take the focus', async () => {
      button.focus();
      await userEvent.keyboard('{Enter}');
      await expect(await within(notifications).findByText('Space created')).toBeInTheDocument();
      await expect(button).toHaveFocus();
    });

    await step('Shift+Tab reaches the toast, Enter closes it, and the focus goes back', async () => {
      await userEvent.tab({ shift: true });
      await expect(within(notifications).getByRole('button', { name: 'Dismiss notification' })).toHaveFocus();
      await userEvent.keyboard('{Enter}');
      await waitFor(() => expect(within(notifications).queryByText('Space created')).not.toBeInTheDocument());
      await expect(button).toHaveFocus();
    });
  },
};

/**
 * More toasts than can show: the oldest ones go, five stay
 */
export const AtMostFive: Story = {
  args: {
    toasts: Array.from({ length: MAX_VISIBLE_TOASTS + 2 }, (_, index) => ({
      severity: 'info' as const,
      summary: `Seedling ${index + 1} is planted`,
      sticky: true,
    })),
  },
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    await waitFor(() => expect(within(notifications).getAllByRole('listitem')).toHaveLength(MAX_VISIBLE_TOASTS));
    await expect(within(notifications).queryByText('Seedling 1 is planted')).toBeNull();
    await expect(within(notifications).getByText('Seedling 7 is planted')).toBeInTheDocument();
  },
};

/**
 * No toast: the region and its list are in the page, empty, ready for the first toast
 */
export const Empty: Story = {
  args: { toasts: [] },
  play: async ({ canvasElement }) => {
    const notifications = region(canvasElement);
    await expect(notifications.querySelector('ol')).toBeEmptyDOMElement();
  },
};

export const LongText: Story = {
  args: {
    toasts: [
      {
        severity: 'warn',
        summary: 'The picture of the community garden in the spring, with the new beds of tomatoes and beans, is too large',
        detail:
          'Choose a picture of 10 MB at most. A smaller picture loads faster for the members who read the posts on a phone in the garden.',
        sticky: true,
      },
    ],
  },
};

export const OnPhone: Story = {
  args: { toasts: [POST_PUBLISHED, SPACE_CREATED] },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
