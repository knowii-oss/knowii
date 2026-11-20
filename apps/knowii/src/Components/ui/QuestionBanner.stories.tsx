import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { Button } from './Button';
import { QuestionBanner } from './QuestionBanner';

/**
 * One plain band at the top of a page that asks one question with two answers ("Show times in Europe/Brussels?"). The
 * answer that changes something comes first, as the primary button; the answer that keeps things as they are is a
 * secondary button. Each answer closes the banner: the component that shows it removes it, and remembers the answer.
 *
 * Not a dialog: the page stays usable and the person may ignore the question. Not a toast: it stays until an answer.
 * Not for a message without a question, and never more than one on a page.
 *
 * Accessibility: a region named by the question. The question is a paragraph, not a heading. The banner never takes the
 * focus when it shows: the two buttons come in the tab order of the page, in their order. An answer that fails shows
 * its error after the buttons, as an alert, and the banner stays. It has no close button.
 */
const meta = {
  component: QuestionBanner,
  parameters: {
    // A band of the whole width, as at the top of a page
    layout: 'fullscreen',
  },
  args: {
    question: 'Show times in Europe/Brussels?',
    actions: (
      <>
        <Button size="sm">Yes, use Europe/Brussels</Button>
        <Button size="sm" variant="secondary">
          Keep Europe/Lisbon
        </Button>
      </>
    ),
  },
} satisfies Meta<typeof QuestionBanner>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const region = canvas.getByRole('region', { name: 'Show times in Europe/Brussels?' });
    // The question is text, not a heading
    await expect(within(region).queryByRole('heading')).toBeNull();
    await expect(within(region).getByText('Show times in Europe/Brussels?').tagName).toBe('P');
    await expect(within(region).queryByRole('alert')).toBeNull();
    // Exactly the buttons that the caller gives, in their order: the banner adds none, and it has no close button
    const buttons = canvas.getAllByRole('button');
    await expect(buttons.map((button) => button.textContent)).toEqual(['Yes, use Europe/Brussels', 'Keep Europe/Lisbon']);
    for (const button of buttons) {
      await expect(region).toContainElement(button);
    }
    await expect(canvas.queryByRole('button', { name: /close|dismiss/i })).toBeNull();
    // The banner does not take the focus when it shows
    await expect(region).not.toContainElement(canvasElement.ownerDocument.activeElement as HTMLElement | null);
    // Tab reaches the two answers in their order
    await userEvent.tab();
    await expect(within(region).getByRole('button', { name: 'Yes, use Europe/Brussels' })).toHaveFocus();
    await userEvent.tab();
    await expect(within(region).getByRole('button', { name: 'Keep Europe/Lisbon' })).toHaveFocus();
  },
};

/**
 * The first answer saves: its button is busy and keeps the focus, and the banner stays. The buttons and their busy state
 * belong to the caller. This is what the time zone question does: `aria-busy` and `aria-disabled` on the answer, no
 * spinner, and the caller ignores a second click.
 */
export const FirstAnswerBusy: Story = {
  args: {
    actions: (
      <>
        <Button size="sm" aria-busy="true" aria-disabled="true">
          Yes, use Europe/Brussels
        </Button>
        <Button size="sm" variant="secondary">
          Keep Europe/Lisbon
        </Button>
      </>
    ),
  },
  play: async ({ canvasElement }) => {
    const region = within(canvasElement).getByRole('region', { name: 'Show times in Europe/Brussels?' });
    // The words of the answer are its whole name: no spinner adds "Loading" to it
    const yes = within(region).getByRole('button', { name: 'Yes, use Europe/Brussels' });
    await expect(yes).toHaveAttribute('aria-busy', 'true');
    await expect(yes).toHaveAttribute('aria-disabled', 'true');
    await expect(yes).not.toBeDisabled();
    await expect(within(region).queryByRole('status')).toBeNull();
    await expect(within(region).getAllByRole('button')).toHaveLength(2);
    await userEvent.tab();
    await expect(yes).toHaveFocus();
    await userEvent.tab();
    await expect(within(region).getByRole('button', { name: 'Keep Europe/Lisbon' })).toHaveFocus();
  },
};

/**
 * The answer failed: the error shows after the buttons, as an alert, and the question stays
 */
export const WithError: Story = {
  args: { error: 'Not saved. Retry.' },
  play: async ({ canvasElement }) => {
    const region = within(canvasElement).getByRole('region', { name: 'Show times in Europe/Brussels?' });
    const alert = within(region).getByRole('alert');
    await expect(alert).toHaveTextContent(/^Not saved\. Retry\.$/);
    // After the buttons, in the order of the page
    const buttons = within(region).getAllByRole('button');
    for (const button of buttons) {
      await expect(button.compareDocumentPosition(alert) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    }
    await expect(region).not.toContainElement(canvasElement.ownerDocument.activeElement as HTMLElement | null);
  },
};

/**
 * Long zone names on a phone: the question wraps and each button goes on its own line
 */
export const LongZoneNamesOnPhone: Story = {
  args: {
    question: 'Show times in America/Argentina/Buenos_Aires?',
    actions: (
      <>
        <Button size="sm">Yes, use America/Argentina/Buenos_Aires</Button>
        <Button size="sm" variant="secondary">
          Keep America/North_Dakota/New_Salem
        </Button>
      </>
    ),
    error: 'Not saved. Retry.',
  },
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('region', { name: 'Show times in America/Argentina/Buenos_Aires?' })).toBeInTheDocument();
  },
};
