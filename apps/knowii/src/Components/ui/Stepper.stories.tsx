import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { Stepper } from './Stepper';

const steps = ['Garden', 'Plants', 'Calendar', 'Summary'] as const;

/**
 * The steps of a form in several parts, above the form: the steps that are done, the current one, and the next ones.
 * The form shows the content of the current step. For a form of one part, do not use it.
 *
 * Accessibility: an ordered list named "Progress". The current step has `aria-current="step"`, and a step that is done
 * says "(completed)" to screen readers (its check mark is hidden from them). The steps are not links: the buttons of the
 * form move between them.
 */
const meta = {
  component: Stepper,
  render: (args) => (
    <div className="max-w-2xl">
      <Stepper {...args} />
    </div>
  ),
  args: {
    steps,
    currentStep: 0,
  },
} satisfies Meta<typeof Stepper>;

export default meta;
type Story = StoryObj<typeof meta>;

export const FirstStep: Story = {
  play: async ({ canvasElement }) => {
    const list = within(canvasElement).getByRole('list', { name: 'Progress' });
    const items = within(list).getAllByRole('listitem');
    await expect(items).toHaveLength(steps.length);
    await expect(items[0]).toHaveAttribute('aria-current', 'step');
    await expect(items[0]).toHaveTextContent('1Garden');
    await expect(list).not.toHaveTextContent('(completed)');
  },
};

export const MiddleStep: Story = {
  args: { currentStep: 2 },
  play: async ({ canvasElement }) => {
    const items = within(within(canvasElement).getByRole('list', { name: 'Progress' })).getAllByRole('listitem');
    await expect(items[0]).toHaveTextContent('Garden (completed)');
    await expect(items[1]).toHaveTextContent('Plants (completed)');
    await expect(items[0]).not.toHaveAttribute('aria-current');
    await expect(items[2]).toHaveAttribute('aria-current', 'step');
    await expect(items[2]).not.toHaveTextContent('(completed)');
    await expect(items[3]).not.toHaveAttribute('aria-current');
  },
};

export const LastStep: Story = {
  args: { currentStep: steps.length - 1 },
  play: async ({ canvasElement }) => {
    const items = within(within(canvasElement).getByRole('list', { name: 'Progress' })).getAllByRole('listitem');
    await expect(items[steps.length - 1]).toHaveAttribute('aria-current', 'step');
  },
};

/**
 * `currentStep` after the last step: every step is done
 */
export const AllDone: Story = {
  args: { currentStep: steps.length },
  play: async ({ canvasElement }) => {
    const list = within(canvasElement).getByRole('list', { name: 'Progress' });
    for (const item of within(list).getAllByRole('listitem')) {
      await expect(item).toHaveTextContent('(completed)');
      await expect(item).not.toHaveAttribute('aria-current');
    }
  },
};

export const TwoSteps: Story = {
  args: { steps: ['Garden', 'Summary'], currentStep: 1 },
};

/**
 * Long names are cut with an ellipsis
 */
export const LongText: Story = {
  args: {
    steps: [
      'The size and the light of the garden',
      'The vegetables and the flowers to grow',
      'The calendar of the sowing and the planting',
      'A summary of the plan before the start',
    ],
    currentStep: 1,
  },
};

export const ManySteps: Story = {
  args: {
    steps: ['Garden', 'Soil', 'Light', 'Water', 'Plants', 'Calendar', 'Summary'],
    currentStep: 3,
  },
};
