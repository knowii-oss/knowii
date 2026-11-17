import type { ButtonHTMLAttributes, ComponentProps, MouseEvent, ReactNode, Ref } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from './cn';
import { Spinner } from './Spinner';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonStyleOptions {
  variant?: ButtonVariant | undefined;
  size?: ButtonSize | undefined;
  fullWidth?: boolean | undefined;
  /**
   * Square button that only holds an icon. Give it an aria-label.
   */
  iconOnly?: boolean | undefined;
}

const variantClasses: Record<ButtonVariant, string> = {
  // "Knowii Brand Style": primary = solid magenta, soft magenta shadow
  primary: 'bg-secondary text-white shadow-glow hover:bg-secondary-hover',
  // Secondary = solid subtle slate, white bold text. Never an outlined pink button.
  secondary: 'bg-overlay/10 text-primary hover:bg-overlay/15',
  danger: 'bg-danger text-white hover:bg-danger-hover',
  ghost: 'bg-transparent text-primary hover:bg-overlay/10',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-base',
  lg: 'h-12 px-6 text-lg',
};

const iconOnlySizeClasses: Record<ButtonSize, string> = {
  sm: 'w-8 px-0 text-sm',
  md: 'w-10 px-0 text-base',
  lg: 'w-12 px-0 text-xl',
};

/**
 * Classes of a button. Use them to style an element that must stay a link (for example an Inertia <Link>).
 */
export function buttonClasses({ variant = 'primary', size = 'md', fullWidth = false, iconOnly = false }: ButtonStyleOptions = {}): string {
  return cn(
    'inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg font-bold whitespace-nowrap select-none',
    'transition-[background-color,box-shadow,transform] duration-150 motion-safe:active:translate-y-px',
    'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
    variantClasses[variant],
    iconOnly ? [sizeClasses[size], iconOnlySizeClasses[size]] : sizeClasses[size],
    fullWidth && 'w-full',
  );
}

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement>, ButtonStyleOptions {
  ref?: Ref<HTMLButtonElement>;
  /**
   * Shows a spinner and makes the button busy. The button keeps the focus (it is not disabled): it gets
   * aria-disabled and aria-busy, and a click (also Enter and Space) does nothing and does not submit the form.
   */
  loading?: boolean | undefined;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
}

/**
 * Button. The default type is "button": set type="submit" to submit a form.
 * `disabled` takes the button out of the tab order. `loading` does not: a disabled button loses the focus (#1564).
 */
export function Button({
  variant,
  size,
  fullWidth,
  iconOnly,
  loading = false,
  leftIcon,
  rightIcon,
  className,
  children,
  disabled,
  type = 'button',
  ref,
  onClick,
  'aria-busy': ariaBusy,
  'aria-disabled': ariaDisabled,
  ...props
}: ButtonProps) {
  const handleClick = (event: MouseEvent<HTMLButtonElement>) => {
    if (loading) {
      // The button is busy: the click does nothing. Prevent the default action, so that a submit button does not
      // submit its form (a click, Enter or Space on the button, or Enter in a field of the form)
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    onClick?.(event);
  };

  return (
    <button
      ref={ref}
      type={type}
      // aria-disabled, not disabled, while loading: a disabled button loses the focus (#1564)
      disabled={disabled}
      aria-disabled={loading || ariaDisabled}
      aria-busy={loading || ariaBusy}
      className={cn(buttonClasses({ variant, size, fullWidth, iconOnly }), className)}
      onClick={handleClick}
      {...props}
    >
      {loading ? <Spinner size="sm" label="Loading" /> : leftIcon}
      {children}
      {!loading && rightIcon}
    </button>
  );
}

export type LinkButtonProps = Omit<ComponentProps<typeof Link>, keyof ButtonStyleOptions> & ButtonStyleOptions;

/**
 * Inertia link that looks like a button. Use it for navigation instead of a <Button> inside a <Link>
 * (a button inside a link is invalid HTML and confuses screen readers).
 * With method="post" (or another non-GET method), Inertia renders a <button> element.
 */
export function LinkButton({ variant, size, fullWidth, iconOnly, className, ...props }: LinkButtonProps) {
  const isGet = props.method === undefined || props.method === 'get';
  return <Link as={isGet ? 'a' : 'button'} {...props} className={cn(buttonClasses({ variant, size, fullWidth, iconOnly }), className)} />;
}
