import type { ReactNode } from 'react';
import { useId } from 'react';
import { cn } from './cn';

export interface SegmentedControlOption<V extends string> {
  value: V;
  label: string;
  icon?: ReactNode;
}

export interface SegmentedControlProps<V extends string> {
  options: readonly SegmentedControlOption<V>[];
  value: V;
  onChange: (value: V) => void;
  onBlur?: () => void;
  /**
   * Name of the radio inputs. Generated when not given.
   */
  name?: string;
  disabled?: boolean;
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
 */
export function SegmentedControl<V extends string>({
  options,
  value,
  onChange,
  onBlur,
  name,
  disabled = false,
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
      className={cn('inline-flex flex-wrap gap-1 rounded-lg border border-border-strong bg-well p-1', !hasDescription && className)}
    >
      {options.map((option) => {
        const checked = option.value === value;
        return (
          <label
            key={option.value}
            className={cn(
              'flex cursor-pointer items-center gap-2 rounded-md px-3 py-1.5 text-sm font-bold text-muted transition-colors select-none',
              'hover:text-primary has-checked:bg-secondary has-checked:text-white has-checked:[&_svg]:text-white',
              'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-primary',
              disabled && 'cursor-not-allowed opacity-60',
            )}
          >
            <input
              type="radio"
              className="sr-only"
              name={groupName}
              value={option.value}
              checked={checked}
              disabled={disabled}
              onChange={() => onChange(option.value)}
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
