import { useId } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaPlus, FaSeedling } from 'react-icons/fa6';
import { Button } from './Button';
import type { CardHeaderProps, CardProps } from './Card';
import { Card, CardBody, CardHeader } from './Card';

const TITLE = 'Recent resources';
const BODY = 'The resources that the members shared this week: seed lists, planting calendars and compost guides.';

interface StoryArgs extends CardProps {
  /**
   * The title of the header; no header without it
   */
  heading?: string | undefined;
  headerIcon?: CardHeaderProps['icon'];
  headerActions?: CardHeaderProps['actions'];
  titleAs?: CardHeaderProps['titleAs'];
}

/**
 * The card as a page shows it: a header with a title, and a body. The title names the section (`aria-labelledby`).
 */
function CardWithHeader({ heading, headerIcon, headerActions, titleAs, children, ...args }: StoryArgs) {
  const titleId = useId();
  return (
    <div className="max-w-xl">
      <Card {...args} {...(heading ? { 'aria-labelledby': titleId } : {})}>
        {heading && (
          <CardHeader
            title={<span id={titleId}>{heading}</span>}
            icon={headerIcon}
            actions={headerActions}
            {...(titleAs ? { titleAs } : {})}
          />
        )}
        <CardBody>{children}</CardBody>
      </Card>
    </div>
  );
}

/**
 * The surface of a group of content on a page (a list of recent posts, the team of a community). `CardHeader` gives it
 * a title with the short magenta bar, and buttons at the end. `CardBody` holds the content. With `interactive`, the
 * card lifts on hover: use it only when the card holds one link or one button that covers it. For a form section,
 * use `FormSection`.
 *
 * Accessibility: a `section` by default. It is a region when it has a name: give it `aria-labelledby` with the id of its
 * title. A card of a list is an `article` (`as="article"`) with an `h3` title (`titleAs="h3"`). The icon and the bar
 * of the header are hidden from screen readers.
 */
const meta = {
  component: Card,
  render: (args) => <CardWithHeader {...args} />,
  args: {
    heading: TITLE,
    children: <p>{BODY}</p>,
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('heading', { level: 2, name: TITLE })).toBeInTheDocument();
    await expect(canvas.getByRole('region', { name: TITLE })).toHaveTextContent(BODY);
  },
};

export const WithIcon: Story = {
  args: { headerIcon: <FaSeedling /> },
  play: async ({ canvasElement }) => {
    const heading = within(canvasElement).getByRole('heading', { level: 2, name: TITLE });
    await expect(heading.querySelector('[aria-hidden="true"] svg')).not.toBeNull();
  },
};

export const WithActions: Story = {
  args: {
    headerIcon: <FaSeedling />,
    headerActions: (
      <Button size="sm" variant="secondary" leftIcon={<FaPlus aria-hidden="true" />}>
        Add a resource
      </Button>
    ),
  },
  play: async ({ canvasElement }) => {
    const region = within(within(canvasElement).getByRole('region', { name: TITLE }));
    await userEvent.tab();
    await expect(region.getByRole('button', { name: 'Add a resource' })).toHaveFocus();
  },
};

/**
 * A card of a list: an `article` with an `h3` title
 */
export const Article: Story = {
  args: { as: 'article', titleAs: 'h3', heading: 'Tomatoes from seed' },
  render: (args) => (
    <ul className="flex max-w-xl flex-col gap-3">
      <li>
        <CardWithHeader {...args} />
      </li>
    </ul>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    await expect(canvas.getByRole('article', { name: 'Tomatoes from seed' })).toBeInTheDocument();
    await expect(canvas.getByRole('heading', { level: 3, name: 'Tomatoes from seed' })).toBeInTheDocument();
  },
};

/**
 * A plain surface, without a header: a `div` is not a region
 */
export const WithoutHeader: Story = {
  args: { as: 'div', heading: undefined },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).queryByRole('region')).toBeNull();
  },
};

/**
 * A card that is one link: it lifts on hover, and the link takes the whole card
 */
export const Interactive: Story = {
  render: () => (
    <div className="max-w-xl">
      <Card as="article" interactive className="overflow-hidden">
        <a
          href="#compost"
          className="block rounded-xl p-5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary"
        >
          <h3 className="text-lg leading-tight font-extrabold">Compost in a small garden</h3>
          <p className="mt-2 text-sm text-muted">Three bins, the right mix of green and brown, and a turn every two weeks.</p>
        </a>
      </Card>
    </div>
  ),
  play: async ({ canvasElement }) => {
    const link = within(canvasElement).getByRole('link', { name: /Compost in a small garden/ });
    await userEvent.tab();
    await expect(link).toHaveFocus();
  },
};

export const LongText: Story = {
  args: {
    heading: 'The resources that the members of the community of the old seed gardeners shared this month',
    headerActions: <Button size="sm">Add a resource</Button>,
    children: (
      <p>
        {BODY} {BODY} {BODY}
      </p>
    ),
  },
};
