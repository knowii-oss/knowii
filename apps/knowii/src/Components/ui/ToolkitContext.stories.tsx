import type { ComponentProps, MouseEvent } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { ApplicationToolkitProvider } from '@/Components/ApplicationToolkit';
import { Breadcrumb } from './Breadcrumb';
import { LinkButton } from './Button';
import type { ToolkitLinkProps } from './ToolkitContext';
import { ToolkitProvider } from './ToolkitContext';

// Outside the application, there is no page to visit: the click stops the visit. Inertia does not visit either when
// the default action of the click is prevented.
function stopVisit(event: MouseEvent): void {
  event.preventDefault();
}

/**
 * A link of another router, as a place that needs another link gives it: it marks itself, so the story can see it
 */
function OtherLink({ as, method, preserveState, preserveScroll, replace, children, ...attributes }: ToolkitLinkProps) {
  return (
    <a data-link="other" {...attributes}>
      {children}
    </a>
  );
}

/**
 * The link of one place, given as a property
 */
function OwnLink({ as, method, preserveState, preserveScroll, replace, children, ...attributes }: ToolkitLinkProps) {
  return (
    <a data-link="own" {...attributes}>
      {children}
    </a>
  );
}

const path = [{ label: 'Home', href: '/dashboard' }, { label: 'Atlas Garden', href: '/communities/atlas-garden' }, { label: 'Settings' }];

/**
 * Some links of the toolkit, as a page shows them
 */
function LinksOfThePage() {
  return (
    <div className="flex flex-col items-start gap-4" onClickCapture={stopVisit}>
      <Breadcrumb items={path} />
      <LinkButton href="/communities" variant="secondary">
        All communities
      </LinkButton>
      <LinkButton href="/logout" method="post" variant="danger">
        Log out
      </LinkButton>
    </div>
  );
}

/**
 * Gives the parts of the application to the toolkit: the link of `LinkButton`, `Breadcrumb` and the toasts
 * (`linkComponent`), and the player of the videos of `MarkdownContent` (`videoRenderer`). Components/ui imports nothing
 * of the application (#657, a lint rule): the application renders the provider once, in `main.tsx`
 * (`ApplicationToolkitProvider`: the Inertia link and the click-to-load `VideoPlayer`). A value that a provider does not
 * give comes from the provider above it, else from the defaults: a plain `<a>` (a `<button>` for a link with another
 * method than GET) and a plain link to the video. One place that needs another value gives it as a property
 * (`linkComponent`, `videoRenderer`).
 *
 * Accessibility: it renders nothing. The link that it gives must stay a link (or a button for a request), with its
 * text as its name.
 */
const meta = {
  component: ToolkitProvider,
  render: (args) => (
    <ToolkitProvider {...args}>
      <LinksOfThePage />
    </ToolkitProvider>
  ),
} satisfies Meta<ComponentProps<typeof ToolkitProvider>>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The defaults: plain links, and a plain button for the request of "Log out"
 */
export const Defaults: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/dashboard');
    const link = canvas.getByRole('link', { name: 'All communities' });
    await expect(link).toHaveAttribute('href', '/communities');
    await expect(canvas.getByRole('button', { name: 'Log out' })).toHaveAttribute('type', 'button');
    const address = canvasElement.ownerDocument.location.href;
    await userEvent.click(link);
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
  },
};

/**
 * In the application: the Inertia link. Its links are Inertia visits (stopped here), and "Log out" is the button that
 * sends the request.
 */
export const InTheApplication: Story = {
  render: () => (
    <ApplicationToolkitProvider>
      <LinksOfThePage />
    </ApplicationToolkitProvider>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('link', { name: 'Atlas Garden' })).toHaveAttribute('href', '/communities/atlas-garden');
    const link = canvas.getByRole('link', { name: 'All communities' });
    const address = canvasElement.ownerDocument.location.href;
    await userEvent.click(link);
    await expect(canvasElement.ownerDocument.location.href).toBe(address);
    await expect(canvas.getByRole('button', { name: 'Log out' })).toBeInTheDocument();
  },
};

/**
 * Another link component for every link below the provider
 */
export const OtherLinkComponent: Story = {
  args: { linkComponent: OtherLink },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('link', { name: 'Home' })).toHaveAttribute('data-link', 'other');
    await expect(canvas.getByRole('link', { name: 'All communities' })).toHaveAttribute('data-link', 'other');
  },
};

/**
 * The property of one component wins over the provider: only that link changes
 */
export const PropertyOverridesTheProvider: Story = {
  render: () => (
    <ToolkitProvider linkComponent={OtherLink}>
      <div className="flex flex-col items-start gap-4" onClickCapture={stopVisit}>
        <Breadcrumb items={path} />
        <ToolkitProvider videoRenderer={() => null}>
          {/* A provider inside keeps the link of the provider above: it gives only the video */}
          <LinkButton href="/communities" variant="secondary">
            All communities
          </LinkButton>
        </ToolkitProvider>
        <LinkButton href="/help" variant="secondary" linkComponent={OwnLink}>
          Help
        </LinkButton>
      </div>
    </ToolkitProvider>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('link', { name: 'Home' })).toHaveAttribute('data-link', 'other');
    await expect(canvas.getByRole('link', { name: 'All communities' })).toHaveAttribute('data-link', 'other');
    await expect(canvas.getByRole('link', { name: 'Help' })).toHaveAttribute('data-link', 'own');
  },
};
