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
  className?: string;
  /**
   * Id of the element that names the group (for example a visible label)
   */
  'aria-labelledby'?: string;
  'aria-label'?: string;
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
}: SegmentedControlProps<V>) {
  const generatedName = useId();
  const groupName = name ?? generatedName;

  return (
    <div
      role="radiogroup"
      aria-labelledby={ariaLabelledBy}
      aria-label={ariaLabel}
      aria-disabled={disabled || undefined}
      className={cn('inline-flex flex-wrap gap-1 rounded-lg border border-border-strong bg-well p-1', className)}
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
}
