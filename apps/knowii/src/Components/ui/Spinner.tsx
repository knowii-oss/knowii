import { cn } from './cn';

export type SpinnerSize = 'sm' | 'md' | 'lg';

const sizeClasses: Record<SpinnerSize, string> = {
  sm: 'size-4 border-2',
  md: 'size-8 border-[3px]',
  lg: 'size-12 border-4',
};

interface SpinnerProps {
  size?: SpinnerSize;
  /**
   * Text for screen readers
   */
  label?: string;
  className?: string;
}

/**
 * Loading indicator, announced to screen readers through role="status"
 */
export function Spinner({ size = 'md', label = 'Loading', className }: SpinnerProps) {
  return (
    <span role="status" className={cn('inline-flex items-center justify-center', className)}>
      <span aria-hidden="true" className={cn('animate-spin rounded-full border-overlay/20 border-t-secondary', sizeClasses[size])} />
      <span className="sr-only">{label}</span>
    </span>
  );
}
