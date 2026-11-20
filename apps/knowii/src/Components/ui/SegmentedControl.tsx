import type { ReactNode } from 'react';
import { useId } from 'react';
import { cn } from './cn';

export interface SegmentedControlOption<V extends string> {
  value: V;
  label: string;
  icon?: ReactNode;
  /**
   * The option cannot be chosen now (a full event). Its radio gets `aria-disabled`, keeps the focus and stays in the
   * arrow order, and the control ignores a change to it. Say why in a text next to the control, and name that text in
   * `describedBy`. Never for the option that is the value.
   */
  ariaDisabled?: boolean | undefined;
  /**
   * Id of the text that describes this option alone, for example why it cannot be chosen
   */
  describedBy?: string | undefined;
}

export interface SegmentedControlProps<V extends string> {
  options: readonly SegmentedControlOption<V>[];
  /**
   * The chosen option. Null: no choice yet, no radio is checked, and the first radio is the tab stop of the group.
   */
  value: V | null;
  onChange: (value: V) => void;
  onBlur?: () => void;
  /**
   * Name of the radio inputs. Generated when not given.
   */
  name?: string;
  disabled?: boolean;
  /**
   * A change of the choice is being saved: the group gets `aria-busy`. The radios stay enabled and keep the focus; the
   * caller decides what a change does meanwhile.
   */
  busy?: boolean | undefined;
  /**
   * Classes of the outer element: the group, or the group and its description when there is one
   */
  className?: string;
  /**
   * Id of the element that names the group (for example a visible label)
   */
  'aria-labelledby'?: string;
  'aria-label'?: string;
  /**
   * Short text under the control, for example what the selected choice means. It is linked to the group with
   * aria-describedby (read when the focus enters the group) and is a polite live region: when it changes with the
   * choice, the new text is read once.
   */
  description?: ReactNode;
}

/**
 * Single choice between a few options, shown side by side. Built on native radio inputs:
 * Tab moves into the group and the arrow keys change the choice.
 *
 * `value` null shows no choice. An option with `ariaDisabled` stays in the group for the keyboard and never becomes the
 * choice. `busy` marks the group while a change is saved, without `disabled`: the focus stays where it is.
 *
 * Forced colors: the fill of the chosen option is lost there, so its label gets a border in the system color `Highlight`.
 */
export function SegmentedControl<V extends string>({
  options,
  value,
  onChange,
  onBlur,
  name,
  disabled = false,
  busy = false,
  className,
  'aria-labelledby': ariaLabelledBy,
  'aria-label': ariaLabel,
  description,
}: SegmentedControlProps<V>) {
  const generatedId = useId();
  const groupName = name ?? generatedId;
  const hasDescription = description !== undefined && description !== null && description !== '';
  const descriptionId = `${generatedId}-description`;

  const group = (
    <div
      role="radiogroup"
      aria-labelledby={ariaLabelledBy}
      aria-label={ariaLabel}
      aria-describedby={hasDescription ? descriptionId : undefined}
      aria-disabled={disabled || undefined}
      aria-busy={busy || undefined}
      className={cn('inline-flex flex-wrap gap-1 rounded-lg border border-border-strong bg-well p-1', !hasDescription && className)}
    >
      {options.map((option) => {
        const checked = option.value === value;
        // Not the option that is the value: it must stay the choice
        const unavailable = option.ariaDisabled === true && !checked;
        return (
          <label
            key={option.value}
            className={cn(
              'flex cursor-pointer items-center gap-2 rounded-md px-3 py-1.5 text-sm font-bold text-muted transition-colors select-none',
              'has-checked:bg-secondary has-checked:text-white has-checked:[&_svg]:text-white',
              // Forced colors remove the fill, and the radio is for screen readers only: a border marks the choice. Every
              // label has the border, in the color of the page, so the choice moves nothing
              'forced-colors:border-2 forced-colors:border-[color:Canvas] forced-colors:has-checked:border-[color:Highlight]',
              'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary',
              !unavailable && 'hover:text-primary',
              (disabled || unavailable) && 'cursor-not-allowed opacity-60',
            )}
          >
            <input
              type="radio"
              className="sr-only"
              name={groupName}
              value={option.value}
              checked={checked}
              disabled={disabled}
              aria-disabled={unavailable || undefined}
              aria-describedby={option.describedBy}
              // The browser checks the radio before this runs. Without a change of `value`, React shows the old choice again
              onChange={() => {
                if (!unavailable) {
                  onChange(option.value);
                }
              }}
              onBlur={onBlur}
            />
            {option.icon && <span aria-hidden="true">{option.icon}</span>}
            {option.label}
          </label>
        );
      })}
    </div>
  );

  if (!hasDescription) {
    return group;
  }

  // The description is on the group only, not on each radio: a screen reader reads it when the focus enters the group,
  // and the live region reads the new text after a change, so it is never read twice
  return (
    <div className={className}>
      {group}
      <p id={descriptionId} aria-live="polite" aria-atomic="true" className="mt-1 text-xs text-muted">
        {description}
      </p>
    </div>
  );
}
