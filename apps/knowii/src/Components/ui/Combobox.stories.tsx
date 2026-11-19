import { useId, useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, waitFor, within } from 'storybook/test';
import type { ComboboxOption, ComboboxProps } from './Combobox';
import { Combobox, COMBOBOX_MAX_OPTIONS } from './Combobox';

const LABEL = 'Plant';

const plants: readonly ComboboxOption<string>[] = [
  { value: 'aubergine', label: 'Aubergine', keywords: ['eggplant'] },
  { value: 'beetroot', label: 'Beetroot' },
  { value: 'broad-bean', label: 'Broad bean', keywords: ['fava bean'] },
  { value: 'carrot', label: 'Carrot' },
  { value: 'courgette', label: 'Courgette', keywords: ['zucchini'] },
  { value: 'jalapeno', label: 'Jalapeño pepper', keywords: ['chili'] },
  { value: 'pak-choi', label: 'Pak choi', keywords: ['bok_choy', 'Brassica/rapa'] },
  { value: 'tomato', label: 'Tomato', keywords: ['love apple'] },
];

const plantsWithDescriptions: readonly ComboboxOption<string>[] = [
  { value: 'beetroot', label: 'Beetroot', description: 'Sow outside from April to July' },
  { value: 'carrot', label: 'Carrot', description: 'Sow outside from March to June' },
  { value: 'tomato', label: 'Tomato', description: 'Sow inside in March, plant out after the last frost' },
];

// As many options as the time zones of a browser
const seedLots: readonly ComboboxOption<string>[] = Array.from({ length: 420 }, (_, index) => ({
  value: `lot-${index + 1}`,
  label: `Seed lot ${index + 1}`,
}));

// The stories give the name of the combobox as `aria-label`: the wrapper below shows it as a visible label or not
type StoryArgs = Extract<ComboboxProps<string>, { 'aria-label': string }> & {
  /**
   * Show the name as a visible label (the combobox then gets `aria-labelledby`); without it, the combobox gets
   * `aria-label`
   */
  withLabel?: boolean;
};

/**
 * The combobox with its label and its value, as a form shows it, and a text after it for a click outside
 */
function LabelledCombobox({ value: valueAtStart, onChange, withLabel = true, 'aria-label': label, ...args }: StoryArgs) {
  const id = useId();
  const labelId = `${id}-label`;
  const [value, setValue] = useState<string | null>(valueAtStart);
  const change = (next: string) => {
    setValue(next);
    onChange(next);
  };
  return (
    <div className="flex max-w-md flex-col gap-1">
      {withLabel && (
        <label id={labelId} htmlFor={id} className="text-sm font-bold">
          {label}
        </label>
      )}
      {withLabel ? (
        <Combobox<string> {...args} id={id} aria-labelledby={labelId} value={value} onChange={change} />
      ) : (
        <Combobox<string> {...args} id={id} aria-label={label} value={value} onChange={change} />
      )}
      <p className="text-sm text-muted">The plant shows on the label of the seed bag.</p>
    </div>
  );
}

/**
 * The field of the story, its status for screen readers, and the page (the list renders outside the canvas, in a portal
 * in the landmark of the field)
 */
function parts(canvasElement: HTMLElement) {
  const canvas = within(canvasElement);
  return {
    field: canvas.getByRole<HTMLInputElement>('combobox', { name: LABEL }),
    status: canvas.getByRole('status'),
    page: within(canvasElement.ownerDocument.body),
  };
}

function optionTexts(listbox: HTMLElement): (string | null)[] {
  return within(listbox)
    .queryAllByRole('option')
    .map((option) => option.textContent);
}

/**
 * A choice of one option in a long list, with a text field that filters the list at each key: the time zones, the
 * members of a community. For a short list, use a `Select`, a `SegmentedControl` or radios: they need no typing. It is
 * not a free text (the value is always one of the options) and not a choice of several values. Name it with
 * `aria-labelledby` (the id of the visible label, which gets `htmlFor` with the `id` of the combobox) or `aria-label`.
 *
 * The default filter is forgiving: the text can be any part of the label or of a keyword, the case and the accents do
 * not count, and spaces, underscores and slashes are the same. The labels that start with the text come first.
 * `pinned` keeps values at the top while the field holds no search text. The list renders 50 options at most, then
 * "Type to see more".
 *
 * Accessibility: a text field with the role `combobox` and a listbox (Headless UI). Typing filters; the arrow keys open
 * the list and move in it, and the field names the active option (`aria-activedescendant`); Enter picks; Escape closes
 * and keeps the old value; Tab leaves and keeps the old value, like a click outside. A polite status says the number of
 * results after each change of the list ("3 results", "1 result", "No results"). Closed, the field shows the label of
 * the value. The button with the arrow is for the mouse and for touch (44 px wide). The open list renders in the
 * landmark of the field (or in its dialog), not on the body.
 */
const meta = {
  component: Combobox<string>,
  render: (args) => <LabelledCombobox {...args} />,
  args: {
    'aria-label': LABEL,
    options: plants,
    value: null,
    onChange: fn(),
    placeholder: 'Type the name of a plant',
  },
  parameters: {
    // The list opens under the field: the docs page gives the story room for it
    docs: { story: { inline: false, iframeHeight: '420px' } },
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path: typing filters at each key, the arrows move, Enter picks, Escape closes and keeps the old value,
 * and Tab leaves and keeps the old value
 */
export const Default: Story = {
  play: async ({ args, canvasElement, step }) => {
    const { field, status, page } = parts(canvasElement);
    await expect(field).toHaveValue('');
    await expect(field).toHaveAttribute('placeholder', 'Type the name of a plant');
    await expect(status).toBeEmptyDOMElement();

    await step('Typing filters the list at each key, and the status says the number of results', async () => {
      await userEvent.tab();
      await expect(field).toHaveFocus();
      await expect(field).toHaveAttribute('aria-expanded', 'false');

      await userEvent.keyboard('b');
      const listbox = await page.findByRole('listbox', { name: LABEL });
      await expect(listbox.closest('main')).toBe(field.closest('main'));
      // The labels that start with "b" first. "b" is also in "bok_choy", a keyword of the pak choi
      await expect(optionTexts(listbox)).toEqual(['Beetroot', 'Broad bean', 'Aubergine', 'Pak choi']);
      await expect(status).toHaveTextContent('4 results');

      await userEvent.keyboard('e');
      await waitFor(() => expect(optionTexts(listbox)).toEqual(['Beetroot', 'Aubergine', 'Broad bean']));
      await expect(status).toHaveTextContent('3 results');
    });

    await step('The arrows move, the field names the active option, and Enter picks it', async () => {
      const listbox = page.getByRole('listbox', { name: LABEL });
      // The first result is active at once: Enter alone picks it
      await waitFor(() =>
        expect(field).toHaveAttribute('aria-activedescendant', within(listbox).getByRole('option', { name: 'Beetroot' }).id),
      );
      await userEvent.keyboard('{ArrowDown}');
      await userEvent.keyboard('{ArrowDown}');
      await expect(field).toHaveAttribute('aria-activedescendant', within(listbox).getByRole('option', { name: 'Broad bean' }).id);
      await userEvent.keyboard('{ArrowUp}');
      await expect(field).toHaveAttribute('aria-activedescendant', within(listbox).getByRole('option', { name: 'Aubergine' }).id);

      await userEvent.keyboard('{Enter}');
      await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
      await expect(args.onChange).toHaveBeenCalledWith('aubergine');
      await expect(field).toHaveValue('Aubergine');
      await expect(field).toHaveFocus();
      await expect(status).toBeEmptyDOMElement();
    });

    await step('The next keys start a new search, and Escape closes and keeps the old value', async () => {
      // The label in the closed field is selected: the keys replace it
      await userEvent.keyboard('to');
      const listbox = await page.findByRole('listbox', { name: LABEL });
      await waitFor(() => expect(optionTexts(listbox)).toEqual(['Tomato']));
      await expect(status).toHaveTextContent('1 result');

      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
      await expect(field).toHaveValue('Aubergine');
      await expect(field).toHaveFocus();
      await expect(args.onChange).toHaveBeenCalledOnce();
    });

    await step('The Down arrow opens the whole list on the value, and Tab leaves and keeps the old value', async () => {
      await userEvent.keyboard('{ArrowDown}');
      const listbox = await page.findByRole('listbox', { name: LABEL });
      await expect(optionTexts(listbox)).toHaveLength(plants.length);
      await expect(status).toHaveTextContent('8 results');
      await expect(within(listbox).getByRole('option', { name: 'Aubergine' })).toHaveAttribute('aria-selected', 'true');
      await userEvent.keyboard('{ArrowDown}');
      await expect(field).toHaveAttribute('aria-activedescendant', within(listbox).getByRole('option', { name: 'Beetroot' }).id);

      await userEvent.tab();
      await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
      await expect(field).not.toHaveFocus();
      await expect(field).toHaveValue('Aubergine');
      await expect(args.onChange).toHaveBeenCalledOnce();
    });
  },
};

/**
 * With the mouse: the button with the arrow opens the list on the chosen value, and the next keys replace the label in
 * the field. The list stays open in the landmark of the field (the main landmark of the page in the tests), so the
 * check of the page passes with it
 */
export const WithValue: Story = {
  args: { value: 'courgette' },
  play: async ({ canvasElement }) => {
    const { field, page } = parts(canvasElement);
    await expect(field).toHaveValue('Courgette');
    await userEvent.click(within(canvasElement).getByRole('button', { name: `Show the list ${LABEL}` }));
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(within(listbox).getByRole('option', { name: 'Courgette' })).toHaveAttribute('aria-selected', 'true');
    await expect(listbox.closest('main')).toBe(field.closest('main'));
    await expect(field).toHaveFocus();
    // The label in the field is selected: the keys replace it, they do not add to it
    await userEvent.keyboard('tom');
    await waitFor(() => expect(optionTexts(listbox)).toEqual(['Tomato']));
    await expect(field).toHaveValue('tom');
  },
};

/**
 * With the mouse: a click on an option picks it, and a click outside the list closes it and keeps the old value, also
 * when the field holds a search text
 */
export const ClickOutside: Story = {
  args: { value: 'carrot' },
  play: async ({ args, canvasElement }) => {
    const { field, page } = parts(canvasElement);
    const canvas = within(canvasElement);
    // A click in the closed field selects its label: the keys replace it
    await userEvent.click(field);
    await userEvent.keyboard('tom');
    await page.findByRole('option', { name: 'Tomato' });

    await userEvent.click(canvas.getByText('The plant shows on the label of the seed bag.'));
    await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
    await expect(field).toHaveValue('Carrot');
    await expect(args.onChange).not.toHaveBeenCalled();

    await userEvent.click(canvas.getByRole('button', { name: `Show the list ${LABEL}` }));
    await userEvent.click(await page.findByRole('option', { name: 'Tomato' }));
    await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
    await expect(field).toHaveValue('Tomato');
    await expect(args.onChange).toHaveBeenCalledOnce();
    await expect(args.onChange).toHaveBeenCalledWith('tomato');
  },
};

/**
 * The pinned values are at the top, in their order, while the field holds no search text. A search text gives the
 * matches without them: the labels that start with the text, then the others, in the order of the options.
 */
export const Pinned: Story = {
  args: { pinned: ['tomato', 'carrot'] },
  play: async ({ canvasElement }) => {
    const { status, page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('{ArrowDown}');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(optionTexts(listbox)).toEqual([
      'Tomato',
      'Carrot',
      'Aubergine',
      'Beetroot',
      'Broad bean',
      'Courgette',
      'Jalapeño pepper',
      'Pak choi',
    ]);
    await expect(status).toHaveTextContent('8 results');

    // "c" is in "chili" and in "choi"
    await userEvent.keyboard('c');
    await waitFor(() => expect(optionTexts(listbox)).toEqual(['Carrot', 'Courgette', 'Jalapeño pepper', 'Pak choi']));
  },
};

/**
 * The default filter: a part of the label or of a keyword, with the case and the accents ignored, and spaces,
 * underscores and slashes the same
 */
export const ForgivingFilter: Story = {
  play: async ({ canvasElement }) => {
    const { field, page } = parts(canvasElement);
    const search = async (text: string) => {
      await userEvent.clear(field);
      await userEvent.type(field, text);
      return optionTexts(await page.findByRole('listbox', { name: LABEL }));
    };

    // The case and the accent of "Jalapeño"
    await expect(await search('JALAPENO')).toEqual(['Jalapeño pepper']);
    // A part of a keyword: "zucchini"
    await expect(await search('cchi')).toEqual(['Courgette']);
    // A space for the underscore of "bok_choy", and for the slash of "Brassica/rapa"
    await expect(await search('bok choy')).toEqual(['Pak choi']);
    await expect(await search('brassica rapa')).toEqual(['Pak choi']);
    // Spaces around the text and between its words do not count
    await expect(await search('  broad   bean ')).toEqual(['Broad bean']);
    // The label that starts with the text comes first, then the others in their order ("eggplant", "pepper", "apple")
    await expect(await search('p')).toEqual(['Pak choi', 'Aubergine', 'Jalapeño pepper', 'Tomato']);
  },
};

/**
 * Nothing matches: the list says so, and the status says it to a screen reader
 */
export const NoResults: Story = {
  args: { value: 'leek', options: [...plants, { value: 'leek', label: 'Leek' }] },
  play: async ({ args, canvasElement }) => {
    const { field, status, page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('xyz');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(optionTexts(listbox)).toEqual([]);
    await expect(within(listbox).getByText('No results')).toBeInTheDocument();
    await expect(status).toHaveTextContent('No results');

    // Enter picks nothing, and the field shows the old value again
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(page.queryByRole('listbox')).not.toBeInTheDocument());
    await expect(field).toHaveValue('Leek');
    await expect(args.onChange).not.toHaveBeenCalled();
  },
};

export const NoResultsWithOwnText: Story = {
  args: { noResultsText: 'No plant has this name' },
  play: async ({ canvasElement }) => {
    const { status, page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('xyz');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(within(listbox).getByText('No plant has this name')).toBeInTheDocument();
    await expect(status).toHaveTextContent('No plant has this name');
  },
};

/**
 * 420 options: the list renders the first 50 and ends with "Type to see more", and the status gives the full number.
 * Each key filters all the options.
 */
export const LongList: Story = {
  args: { options: seedLots, placeholder: 'Type the number of a seed lot' },
  play: async ({ canvasElement }) => {
    const { status, page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('{ArrowDown}');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(optionTexts(listbox)).toHaveLength(COMBOBOX_MAX_OPTIONS);
    await expect(within(listbox).getByText('Type to see more')).toBeInTheDocument();
    await expect(status).toHaveTextContent('420 results. Type to see more.');

    // "Seed lot 4", "Seed lot 40" to "49", "Seed lot 400" to "420"
    await userEvent.keyboard('lot 4');
    await waitFor(() => expect(optionTexts(listbox)).toHaveLength(32));
    await expect(within(listbox).queryByText('Type to see more')).toBeNull();
    await expect(status).toHaveTextContent('32 results');

    await userEvent.keyboard('2');
    await waitFor(() => expect(optionTexts(listbox)).toEqual(['Seed lot 42', 'Seed lot 420']));
    await expect(status).toHaveTextContent('2 results');
  },
};

/**
 * An own filter: here only the start of the label counts, so "b" does not find "Aubergine"
 */
export const WithOwnFilter: Story = {
  args: {
    filter: (option, query) => option.label.toLowerCase().startsWith(query.trim().toLowerCase()),
  },
  play: async ({ canvasElement }) => {
    const { page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('b');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(optionTexts(listbox)).toEqual(['Beetroot', 'Broad bean']);
  },
};

/**
 * Each option has a second line. The name of the option holds both lines.
 */
export const WithDescriptions: Story = {
  args: { options: plantsWithDescriptions, value: 'carrot' },
  play: async ({ canvasElement }) => {
    const { page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('{ArrowDown}');
    const listbox = await page.findByRole('listbox', { name: LABEL });
    await expect(within(listbox).getByRole('option', { name: 'Carrot Sow outside from March to June' })).toHaveAttribute(
      'aria-selected',
      'true',
    );
  },
};

export const WithoutVisibleLabel: Story = {
  args: { withLabel: false },
  play: async ({ canvasElement }) => {
    const { field, page } = parts(canvasElement);
    await expect(field).toHaveAttribute('aria-label', LABEL);
    await userEvent.click(within(canvasElement).getByRole('button', { name: `Show the list: ${LABEL}` }));
    await expect(await page.findByRole('listbox', { name: LABEL })).toBeInTheDocument();
  },
};

export const Disabled: Story = {
  args: { disabled: true, value: 'tomato' },
  play: async ({ canvasElement }) => {
    const { field, page } = parts(canvasElement);
    await expect(field).toBeDisabled();
    await expect(field).toHaveValue('Tomato');
    const button = within(canvasElement).getByRole('button', { name: `Show the list ${LABEL}` });
    await expect(button).toBeDisabled();
    await userEvent.click(button);
    await expect(page.queryByRole('listbox')).toBeNull();
  },
};

/**
 * Long labels wrap in the list: on a phone, the list is as wide as the field and the page does not scroll sideways
 */
export const LongTextOnPhone: Story = {
  args: {
    options: [
      {
        value: 'tomato',
        label: 'Tomato: sow the seeds inside in March, and plant the seedlings out after the last frost',
        description: 'Water at the foot, in the morning, and pick the fruits when they are ripe',
      },
      {
        value: 'beetroot',
        label: 'Beetroot: sow outside from April to July, and thin the seedlings when they have four leaves',
        description: 'Keep the soil wet in dry weeks, and pick the roots before they get woody',
      },
    ],
    value: 'tomato',
  },
  globals: { viewport: { value: 'phone', isRotated: false } },
  play: async ({ canvasElement }) => {
    const { page } = parts(canvasElement);
    await userEvent.tab();
    await userEvent.keyboard('{ArrowDown}');
    await expect(await page.findByRole('listbox', { name: LABEL })).toBeInTheDocument();
  },
};
