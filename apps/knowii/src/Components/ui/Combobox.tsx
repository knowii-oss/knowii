import type { ComponentProps, RefObject } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import {
  Combobox as HeadlessCombobox,
  ComboboxButton,
  ComboboxInput,
  ComboboxOption as HeadlessComboboxOption,
  ComboboxOptions,
  Portal,
} from '@headlessui/react';
import { FaCheck, FaChevronDown } from 'react-icons/fa';
import { cn } from './cn';
import { formControlClasses, formControlSizeClasses } from './form-field';
import { useLandmarkPortalTarget } from './landmark-portal';

export interface ComboboxOption<V extends string> {
  value: V;
  label: string;
  /**
   * A second line under the label, in a smaller text. The filter does not read it.
   */
  description?: string;
  /**
   * Other words that find the option: the filter reads them like the label. They are not shown.
   */
  keywords?: readonly string[];
}

/**
 * The name of the field and of its list: the text itself, or the id of the visible label
 */
type ComboboxName =
  | { 'aria-label': string; 'aria-labelledby'?: never }
  | {
      /**
       * Id of the visible label. Give the label `htmlFor` with the `id` of the combobox, so that a click on it gives
       * the focus to the field.
       */
      'aria-labelledby': string;
      'aria-label'?: never;
    };

export type ComboboxProps<V extends string> = ComboboxName & {
  /**
   * Id of the text field. A <label htmlFor={id}> gives it the focus.
   */
  id?: string;
  options: readonly ComboboxOption<V>[];
  /**
   * Selected value. A value that matches no option shows the placeholder.
   */
  value: V | null;
  onChange: (value: V) => void;
  placeholder?: string;
  disabled?: boolean;
  /**
   * Marks the field as invalid (`aria-invalid`, and the border of an error). Show the error as text near the field,
   * and name its element with `aria-describedby`.
   */
  invalid?: boolean;
  /**
   * Ids of the elements that describe the field: its hint, its error
   */
  'aria-describedby'?: string | undefined;
  /**
   * Values that stay at the top of the list, in this order, while the field holds no search text. With a search text,
   * the list holds the matches whose label starts with the text, then the other matches, both in the order of
   * `options`.
   */
  pinned?: readonly V[];
  /**
   * Whether an option matches the search text. Default: `comboboxOptionMatches`.
   */
  filter?: (option: ComboboxOption<V>, query: string) => boolean;
  /**
   * The text of the list, and of the status for screen readers, when no option matches
   */
  noResultsText?: string;
};

/**
 * The most options that the list renders. When more match, the list ends with "Type to see more": a long list stays
 * fast (the 420 time zones), and nobody scrolls through hundreds of lines.
 */
export const COMBOBOX_MAX_OPTIONS = 50;

const MORE_TEXT = 'Type to see more';

/**
 * A text as the filter compares it: lower case, without accents, and every run of other characters than letters,
 * digits and "+" as one space. "America/New_York" and "new  york" give "america new york" and "new york".
 */
function searchText(text: string): string {
  return text
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}+]+/gu, ' ')
    .trim();
}

/**
 * The default filter of the Combobox (#1666): the search text is a part of the label or of a keyword. Case and accents
 * are ignored, and spaces, underscores, slashes and other punctuation are the same: "new york" finds
 * "America/New_York", "brus" finds "Europe/Brussels", "sao" finds "São Paulo".
 */
export function comboboxOptionMatches<V extends string>(option: ComboboxOption<V>, query: string): boolean {
  const wanted = searchText(query);
  return [option.label, ...(option.keywords ?? [])].some((text) => searchText(text).includes(wanted));
}

/**
 * "12 results", "1 result", or the text for no result. With more matches than the list renders, it says how to get the
 * others.
 */
function resultsText(count: number, shown: number, noResultsText: string): string {
  if (count === 0) {
    return noResultsText;
  }
  const results = count === 1 ? '1 result' : `${count} results`;
  return count > shown ? `${results}. ${MORE_TEXT}.` : results;
}

// Headless UI sets aria-labelledby (and aria-describedby of the field) of its parts itself, over the one of the props
// (see SelectButton): the parts below take the ids under another name

function ComboboxField({
  labelledBy,
  describedBy,
  ...props
}: ComponentProps<'input'> & { labelledBy?: string | undefined; describedBy?: string | undefined }) {
  return <input {...props} aria-labelledby={labelledBy} aria-describedby={describedBy} />;
}

function ComboboxToggle({ labelledBy, ...props }: ComponentProps<'button'> & { labelledBy?: string | undefined }) {
  return <button {...props} aria-labelledby={labelledBy} />;
}

function ComboboxList({ labelledBy, ...props }: ComponentProps<'div'> & { labelledBy?: string | undefined }) {
  return <div {...props} aria-labelledby={labelledBy} />;
}

/**
 * Selects the text of the field when the list opens without a search text (the arrow keys, the button) and when it
 * closes: the field then holds the label of the value, and the next key must start a new search, not add a letter to
 * the label. Only with the focus in the field. It renders after the Headless UI input, so its effect runs after the one
 * that writes the label in the field.
 */
function SelectLabelOnToggle({ open, searching, input }: { open: boolean; searching: boolean; input: RefObject<HTMLInputElement | null> }) {
  useEffect(() => {
    const field = input.current;
    if (!searching && field && field.ownerDocument.activeElement === field) {
      field.select();
    }
  }, [open, searching, input]);
  return null;
}

/**
 * A choice of one option in a long list, with a text field that filters the list at each key (#1666): the time zones,
 * later the members or the spaces of a community. Built on the Headless UI Combobox.
 *
 * Not for a short list (use a `Select`, a `SegmentedControl` or radios: they need no typing), not for a free text (the
 * value is always one of the options), and not for a choice of several values.
 *
 * Mouse and touch: a click in the field or on its button opens the list.
 * Keyboard: typing filters, the arrows open the list and move in it, Enter picks the active option, Escape closes and
 * keeps the old value, and Tab leaves and keeps the old value, like a click outside. Closed, the field shows the label
 * of the value, selected, so that typing starts a new search. A polite status says the number of results after each
 * change of the list. An error: `invalid` marks the field, and `aria-describedby` names the text of the error, which
 * the form shows. The list renders at most `COMBOBOX_MAX_OPTIONS` options, in the landmark of the field (or in its
 * dialog), not on the body.
 */
export function Combobox<V extends string>({
  id,
  options,
  value,
  onChange,
  placeholder = 'Type to search',
  disabled = false,
  invalid = false,
  'aria-describedby': ariaDescribedBy,
  pinned,
  filter = comboboxOptionMatches,
  noResultsText = 'No results',
  'aria-label': ariaLabel,
  'aria-labelledby': ariaLabelledBy,
}: ComboboxProps<V>) {
  const [query, setQuery] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);
  const listTarget = useLandmarkPortalTarget(inputRef);
  const toggleId = useId();
  // Headless UI picks the active option when Tab leaves the open list. Here Tab keeps the old value, like Escape.
  const leavingWithTab = useRef(false);

  const selected = options.find((option) => option.value === value);
  const labels = useMemo(() => new Map(options.map((option) => [option.value, option.label])), [options]);

  const hasQuery = query.trim() !== '';
  const matches = useMemo(() => {
    if (hasQuery) {
      // The labels that start with the search text come first: "utc" gives the zone "UTC" before the hundreds of
      // labels that end with "(UTC+1)". Each group keeps the order of the options.
      const wanted = searchText(query);
      const found = options.filter((option) => filter(option, query));
      const startsWithQuery = (option: ComboboxOption<V>) => searchText(option.label).startsWith(wanted);
      return [...found.filter(startsWithQuery), ...found.filter((option) => !startsWithQuery(option))];
    }
    // No search text: the pinned options first, then the others in their order
    const pinnedValues = new Set<V>(pinned);
    const pinnedOptions = [...pinnedValues].flatMap((pinnedValue) => options.find((option) => option.value === pinnedValue) ?? []);
    return [...pinnedOptions, ...options.filter((option) => !pinnedValues.has(option.value))];
  }, [options, pinned, filter, query, hasQuery]);
  const shown = matches.slice(0, COMBOBOX_MAX_OPTIONS);

  return (
    <HeadlessCombobox
      value={selected ? selected.value : null}
      // null is the field emptied by the person: the value stays, and comes back in the field when the list closes
      onChange={(newValue: V | null) => {
        if (newValue !== null && !leavingWithTab.current) {
          onChange(newValue);
        }
      }}
      onClose={() => setQuery('')}
      disabled={disabled}
      invalid={invalid}
    >
      {({ open }) => (
        <div className="relative">
          <ComboboxInput
            as={ComboboxField}
            ref={inputRef}
            id={id}
            aria-label={ariaLabel}
            labelledBy={ariaLabelledBy}
            describedBy={ariaDescribedBy}
            aria-invalid={invalid || undefined}
            placeholder={placeholder}
            autoComplete="off"
            displayValue={(shownValue: V | null) => (shownValue === null ? '' : (labels.get(shownValue) ?? ''))}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Tab') {
                // This handler runs before the one of Headless UI, in the same call: the pick that follows is ignored
                leavingWithTab.current = true;
                queueMicrotask(() => {
                  leavingWithTab.current = false;
                });
              }
            }}
            // A click in the closed field opens the list, like the button does, and selects the label: typing replaces
            // it. The focus alone (the Tab key) does not open it. Headless UI gives no function to open the list: its
            // button opens it on a pointer down of the mouse, so the field sends that event to the button
            onClick={(event) => {
              if (!open) {
                event.currentTarget.select();
                document
                  .getElementById(toggleId)
                  ?.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, pointerType: 'mouse', button: 0 }));
              }
            }}
            className={cn(formControlClasses, formControlSizeClasses.md, 'pr-11 data-open:border-primary')}
          />
          <SelectLabelOnToggle open={open} searching={hasQuery} input={inputRef} />
          {/* For the mouse and for touch: the keyboard opens the list with the arrows (Headless UI keeps the button out
              of the Tab order). 44 px wide. */}
          <ComboboxButton
            as={ComboboxToggle}
            id={toggleId}
            aria-label={ariaLabel === undefined ? 'Show the list' : `Show the list: ${ariaLabel}`}
            labelledBy={ariaLabelledBy === undefined ? undefined : `${toggleId} ${ariaLabelledBy}`}
            className="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center rounded-r-lg text-muted hover:text-primary disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FaChevronDown aria-hidden="true" className="size-3" />
          </ComboboxButton>
          {/* Stays in the page, empty while the list is closed: a screen reader reads each change of its text */}
          <span role="status" aria-live="polite" className="sr-only">
            {open ? resultsText(matches.length, shown.length, noResultsText) : ''}
          </span>
          {/* The open list renders in the landmark of the field, not on the body (#1651), like the list of Select */}
          <Portal.Group target={listTarget}>
            <ComboboxOptions
              as={ComboboxList}
              anchor={{ to: 'bottom start', gap: 4 }}
              transition
              // The page behind the list still scrolls and still takes a click: the person types in a field of the page
              modal={false}
              aria-label={ariaLabel}
              labelledBy={ariaLabelledBy}
              className={cn(
                'z-50 w-(--input-width) rounded-lg border border-border bg-surface-elevated p-1 shadow-xl [--anchor-max-height:22rem] focus:outline-none',
                'transition duration-100 ease-out data-closed:opacity-0 motion-reduce:transition-none',
              )}
            >
              {shown.map((option, index) => (
                <HeadlessComboboxOption
                  key={option.value}
                  value={option.value}
                  order={index}
                  // The active option has an outline too: a fill alone is lost in forced colors
                  className={cn(
                    'group flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm text-primary select-none md:min-h-0',
                    'data-focus:bg-overlay/10 data-focus:outline-2 data-focus:-outline-offset-2 data-focus:outline-primary data-selected:font-bold',
                  )}
                >
                  <span className="min-w-0 grow wrap-anywhere">
                    <span className="block">{option.label}</span>
                    {option.description !== undefined && (
                      // The space keeps the two lines apart in the name of the option
                      <>
                        {' '}
                        <span className="block text-xs font-normal text-muted">{option.description}</span>
                      </>
                    )}
                  </span>
                  <FaCheck aria-hidden="true" className="invisible size-3 shrink-0 text-secondary-text group-data-selected:visible" />
                </HeadlessComboboxOption>
              ))}
              {/* A listbox holds only options: the two lines below are for the eyes, and the status says the same */}
              {matches.length === 0 && (
                <div aria-hidden="true" className="px-3 py-2 text-sm text-muted">
                  {noResultsText}
                </div>
              )}
              {matches.length > shown.length && (
                <div aria-hidden="true" className="px-3 py-2 text-sm text-muted">
                  {MORE_TEXT}
                </div>
              )}
            </ComboboxOptions>
          </Portal.Group>
        </div>
      )}
    </HeadlessCombobox>
  );
}
