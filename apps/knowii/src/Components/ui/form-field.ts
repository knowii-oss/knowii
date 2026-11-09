/**
 * Shared look of the text form controls (input, textarea, select button)
 */
export const formControlClasses =
  'block w-full rounded-lg border border-border-strong bg-well text-primary placeholder:text-muted ' +
  'transition-colors hover:border-overlay/60 ' +
  'focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary ' +
  'disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:border-border-strong ' +
  'aria-invalid:border-danger-text';

export type FormControlSize = 'sm' | 'md' | 'lg';

export const formControlSizeClasses: Record<FormControlSize, string> = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-11 px-3 text-base',
  lg: 'h-12 px-4 text-lg',
};

/**
 * Left padding when a leading icon is shown
 */
export const formControlIconPaddingClasses: Record<FormControlSize, string> = {
  sm: 'pl-9',
  md: 'pl-10',
  lg: 'pl-11',
};
