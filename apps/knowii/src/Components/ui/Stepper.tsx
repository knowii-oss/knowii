import { FaCheck } from 'react-icons/fa';
import { cn } from './cn';

interface StepperProps {
  steps: readonly string[];
  /**
   * Index of the current step (0-based)
   */
  currentStep: number;
  className?: string;
}

/**
 * Progress indicator of a multi-step form. The form shows the content of the current step.
 */
export function Stepper({ steps, currentStep, className }: StepperProps) {
  return (
    <ol aria-label="Progress" className={cn('flex items-center gap-2', className)}>
      {steps.map((step, index) => {
        const done = index < currentStep;
        const current = index === currentStep;
        return (
          <li key={step} aria-current={current ? 'step' : undefined} className="flex min-w-0 grow items-center gap-2 last:grow-0">
            <span
              className={cn(
                'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                current && 'bg-secondary text-white',
                done && 'bg-overlay/15 text-primary',
                !current && !done && 'border border-border-strong text-muted',
              )}
            >
              {done ? <FaCheck aria-hidden="true" className="size-3" /> : index + 1}
            </span>
            <span className={cn('truncate text-sm font-bold', current ? 'text-primary' : 'text-muted')}>
              {step}
              {done && <span className="sr-only"> (completed)</span>}
            </span>
            {index < steps.length - 1 && <span aria-hidden="true" className="h-px min-w-4 grow bg-border-strong" />}
          </li>
        );
      })}
    </ol>
  );
}
