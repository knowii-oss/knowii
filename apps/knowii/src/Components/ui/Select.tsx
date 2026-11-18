import type { ComponentProps, ReactNode } from 'react';
import { useRef } from 'react';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions, Portal } from '@headlessui/react';
import { FaCheck, FaChevronDown } from 'react-icons/fa';
import { cn } from './cn';
import type { FormControlSize } from './form-field';
import { formControlClasses, formControlSizeClasses } from './form-field';
import { useLandmarkPortalTarget } from './landmark-portal';

export interface SelectOption<V extends string> {
  value: V;
  label: string;
  /**
   * Decorative icon shown before the label
   */
  icon?: ReactNode;
}

export interface SelectProps<V extends string> {
  /**
   * Id of the button. A <label htmlFor={id}> names it.
   */
  id?: string;
  options: readonly SelectOption<V>[];
  /**
   * Selected value. A value that matches no option shows the placeholder.
   */
  value: V | null;
  onChange: (value: V) => void;
  placeholder?: string;
  disabled?: boolean;
  invalid?: boolean;
  size?: FormControlSize;
  className?: string;
  'aria-label'?: string;
  /**
   * Ids of the texts that describe the select: its hint, its error
   */
  'aria-describedby'?: string | undefined;
}

/**
 * The button of the select. Headless UI gives its button its own aria-describedby (empty without its Description
 * component), over the one of the props: this button takes the ids under another name (#1651).
 */
function SelectButton({ describedBy, ...props }: ComponentProps<'button'> & { describedBy?: string | undefined }) {
  return <button {...props} aria-describedby={describedBy} />;
}

/**
 * Single-choice select built on the Headless UI Listbox (keyboard: arrows, Home/End, type-ahead, Enter, Escape)
 */
export function Select<V extends string>({
  id,
  options,
  value,
  onChange,
  placeholder = 'Select an option',
  disabled = false,
  invalid = false,
  size = 'md',
  className,
  'aria-label': ariaLabel,
  'aria-describedby': ariaDescribedBy,
}: SelectProps<V>) {
  const selected = options.find((option) => option.value === value);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const listTarget = useLandmarkPortalTarget(buttonRef);

  return (
    <Listbox
      value={selected ? selected.value : null}
      onChange={(newValue: V | null) => newValue !== null && onChange(newValue)}
      disabled={disabled}
    >
      <ListboxButton
        as={SelectButton}
        ref={buttonRef}
        id={id}
        aria-label={ariaLabel}
        describedBy={ariaDescribedBy}
        aria-invalid={invalid || undefined}
        className={cn(
          formControlClasses,
          formControlSizeClasses[size],
          'flex cursor-pointer items-center justify-between gap-2 text-left data-open:border-primary',
          className,
        )}
      >
        <span className={cn('flex min-w-0 items-center gap-2 truncate', !selected && 'text-muted')}>
          {selected?.icon && <span aria-hidden="true">{selected.icon}</span>}
          {selected ? selected.label : placeholder}
        </span>
        <FaChevronDown aria-hidden="true" className="size-3 shrink-0 text-muted" />
      </ListboxButton>
      {/* The open list renders in the landmark of the button, not on the body (#1651). Portal.Group is the only export
          of the PortalGroup of Headless UI */}
      <Portal.Group target={listTarget}>
        <ListboxOptions
          anchor={{ to: 'bottom start', gap: 4 }}
          transition
          className={cn(
            'z-50 w-(--button-width) min-w-48 rounded-lg border border-border bg-surface-elevated p-1 shadow-xl focus:outline-none',
            'transition duration-100 ease-out data-closed:opacity-0 motion-reduce:transition-none',
          )}
        >
          {options.map((option) => (
            <ListboxOption
              key={option.value}
              value={option.value}
              className="group flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm text-primary select-none data-focus:bg-overlay/10 data-selected:font-bold"
            >
              {option.icon && <span aria-hidden="true">{option.icon}</span>}
              <span className="grow">{option.label}</span>
              <FaCheck aria-hidden="true" className="invisible size-3 text-secondary-text group-data-selected:visible" />
            </ListboxOption>
          ))}
        </ListboxOptions>
      </Portal.Group>
    </Listbox>
  );
}
