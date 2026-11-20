import type { MouseEvent } from 'react';
import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, fn, userEvent, waitFor, within } from 'storybook/test';
import { FaSeedling } from 'react-icons/fa6';
import { Button } from './Button';
import type { DrawerProps } from './Drawer';
import { Drawer } from './Drawer';

const TRIGGER_NAME = 'Open the menu';

// Outside the application, a link of the menu must not leave the story: a click on it stops the visit. The handler
// sits on the navigation itself, inside the panel: a click in the panel does not go up to the wrapper of the story.
function stopVisit(event: MouseEvent): void {
  if (event.target instanceof Element && event.target.closest('a')) {
    event.preventDefault();
  }
}

function focusedElement(document: Document): HTMLElement | null {
  return document.activeElement instanceof HTMLElement ? document.activeElement : null;
}

function SpaceLinks() {
  return (
    <nav aria-label="Spaces" onClick={stopVisit}>
      <ul className="flex flex-col gap-1">
        {['Vegetables', 'Herbs', 'Fruit trees', 'Seeds to share'].map((name) => (
          <li key={name}>
            <a
              href={`/communities/atlas-garden/${name.toLowerCase().replaceAll(' ', '-')}`}
              className="touch-target flex items-center rounded-lg px-3 py-2 font-medium hover:bg-overlay/10"
            >
              {name}
            </a>
          </li>
        ))}
      </ul>
    </nav>
  );
}

/**
 * The button that opens the drawer, and the drawer. The story args set the state at the start.
 */
function DrawerWithTrigger({ open: openAtStart, onClose, ...args }: DrawerProps) {
  const [open, setOpen] = useState(openAtStart);
  return (
    <div>
      <Button onClick={() => setOpen(true)}>{TRIGGER_NAME}</Button>
      <Drawer
        {...args}
        open={open}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
      />
    </div>
  );
}

/**
 * A panel that slides in from the side of the screen, for the navigation of a community on a phone, or for a list of
 * filters. It is modal: the page behind it waits. For a short task with a form, use a `Dialog`.
 *
 * Accessibility: a modal dialog named by its title. The focus goes inside when it opens (to the drawer itself: Tab then
 * reaches the close button), stays inside while it is open, and goes back to the button that opened it. Escape, a
 * click outside the panel and the close button close it; the close button is named "Close menu" by default.
 */
const meta = {
  component: Drawer,
  render: (args) => <DrawerWithTrigger {...args} />,
  args: {
    open: true,
    onClose: fn(),
    title: 'Atlas Garden',
    children: <SpaceLinks />,
  },
  parameters: {
    // Each story of the docs page renders in its own frame: an open drawer covers the whole frame
    docs: { story: { inline: false, iframeHeight: '480px' } },
  },
} satisfies Meta<typeof Drawer>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: the drawer opens, the focus goes inside, Tab stays inside, Escape closes, and the focus goes back
 * to the button
 */
export const KeyboardPath: Story = {
  args: { open: false },
  play: async ({ args, canvasElement, step }) => {
    const document = canvasElement.ownerDocument;
    const trigger = within(canvasElement).getByRole('button', { name: TRIGGER_NAME });
    // The drawer renders outside the canvas (a portal on the body)
    const page = within(document.body);

    await step('Enter on the button opens the drawer', async () => {
      await userEvent.tab();
      await expect(trigger).toHaveFocus();
      await userEvent.keyboard('{Enter}');
    });

    const drawer = await page.findByRole('dialog', { name: 'Atlas Garden' });

    await step('The focus goes to the drawer, and Tab to the close button', async () => {
      await waitFor(() => expect(drawer).toHaveFocus());
      await userEvent.tab();
      await expect(within(drawer).getByRole('button', { name: 'Close menu' })).toHaveFocus();
    });

    await step('Tab and Shift+Tab keep the focus inside the drawer', async () => {
      // The close button and the four links: one more Tab than that goes round once
      for (let index = 0; index < 5; index++) {
        await userEvent.tab();
        await waitFor(() => expect(drawer).toContainElement(focusedElement(document)));
      }
      await userEvent.tab({ shift: true });
      await waitFor(() => expect(drawer).toContainElement(focusedElement(document)));
    });

    await step('Escape closes the drawer', async () => {
      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('dialog')).not.toBeInTheDocument());
      await expect(args.onClose).toHaveBeenCalledOnce();
    });

    await step('The focus goes back to the button', async () => {
      await waitFor(() => expect(trigger).toHaveFocus());
    });
  },
};

/**
 * The close button closes the drawer and gives the focus back to the button that opened it. A link of the menu does
 * not leave the story.
 */
export const CloseButton: Story = {
  args: { open: false },
  play: async ({ args, canvasElement }) => {
    const document = canvasElement.ownerDocument;
    const trigger = within(canvasElement).getByRole('button', { name: TRIGGER_NAME });
    await userEvent.click(trigger);
    const drawer = await within(document.body).findByRole('dialog', { name: 'Atlas Garden' });

    // Outside the application a link must not leave the story: the click is stopped (the result is false)
    const address = document.location.href;
    await expect(await fireEvent.click(within(drawer).getByRole('link', { name: 'Herbs' }))).toBe(false);
    await expect(document.location.href).toBe(address);

    await userEvent.click(within(drawer).getByRole('button', { name: 'Close menu' }));
    await waitFor(() => expect(within(document.body).queryByRole('dialog')).not.toBeInTheDocument());
    await expect(args.onClose).toHaveBeenCalledOnce();
    await waitFor(() => expect(trigger).toHaveFocus());
  },
};

export const FromTheLeft: Story = {
  play: async ({ canvasElement }) => {
    await expect(await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Atlas Garden' })).toBeInTheDocument();
  },
};

/**
 * From the right, with a close button that says what it closes
 */
export const FromTheRight: Story = {
  args: {
    side: 'right',
    title: 'Filters',
    closeLabel: 'Close the filters',
    children: <p className="text-sm text-muted">Show the posts about one kind of plant, or one season.</p>,
  },
  play: async ({ canvasElement }) => {
    const drawer = await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Filters' });
    await expect(within(drawer).getByRole('button', { name: 'Close the filters' })).toBeInTheDocument();
  },
};

/**
 * A title with an icon: the icon is hidden from screen readers, the name stays the text
 */
export const TitleWithIcon: Story = {
  args: {
    title: (
      <>
        <FaSeedling aria-hidden="true" className="text-secondary-text" />
        Atlas Garden
      </>
    ),
  },
  play: async ({ canvasElement }) => {
    await expect(await within(canvasElement.ownerDocument.body).findByRole('dialog', { name: 'Atlas Garden' })).toBeInTheDocument();
  },
};

/**
 * On a phone the drawer takes the whole width
 */
export const OnPhone: Story = {
  globals: { viewport: { value: 'phone', isRotated: false } },
};

/**
 * A long list scrolls inside the drawer; the title and the close button stay at the top
 */
export const LongText: Story = {
  args: {
    title: 'The community of the gardeners who grow old seeds and share them every spring',
    children: (
      <div className="flex flex-col gap-4">
        <SpaceLinks />
        {Array.from({ length: 12 }, (_, index) => (
          <p key={index} className="text-sm text-muted">
            A long text scrolls the drawer, not the page behind it. The title and the close button stay where they are.
          </p>
        ))}
      </div>
    ),
  },
};
