import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { FaCircleCheck, FaCircleInfo, FaCircleXmark, FaTriangleExclamation, FaXmark } from 'react-icons/fa6';
import type { Toast, ToastSeverity } from '@knowii/common';
import { DEFAULT_TOAST_LIFE, useToasts } from '@knowii/common';
import { cn } from './cn';

const severityIcons: Record<ToastSeverity, ReactNode> = {
  success: <FaCircleCheck className="text-success" />,
  info: <FaCircleInfo className="text-primary" />,
  warn: <FaTriangleExclamation className="text-warning" />,
  error: <FaCircleXmark className="text-danger-text" />,
};

const severityBarClasses: Record<ToastSeverity, string> = {
  success: 'bg-success',
  info: 'bg-overlay/60',
  warn: 'bg-warning',
  error: 'bg-danger-text',
};

interface ToastItemProps {
  toast: Toast;
  onDismiss: (id: string) => void;
}

function ToastItem({ toast, onDismiss }: ToastItemProps) {
  const [paused, setPaused] = useState(false);
  const remaining = useRef(toast.life ?? DEFAULT_TOAST_LIFE);

  // Auto-dismiss, paused while the pointer or the focus is on the toast (WCAG 2.2.1: timing adjustable)
  useEffect(() => {
    if (toast.sticky || paused) {
      return;
    }
    const startedAt = Date.now();
    const timer = window.setTimeout(() => onDismiss(toast.id), remaining.current);
    return () => {
      window.clearTimeout(timer);
      remaining.current -= Date.now() - startedAt;
    };
  }, [paused, toast.id, toast.sticky, onDismiss]);

  return (
    <li
      role={toast.severity === 'error' ? 'alert' : undefined}
      className="pointer-events-auto relative flex w-full max-w-md animate-toast-in items-start gap-3 overflow-hidden rounded-xl border border-border bg-surface-elevated py-3 pr-2 pl-5 text-primary shadow-2xl motion-reduce:animate-none"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
      <span aria-hidden="true" className={cn('absolute inset-y-0 left-0 w-1.5', severityBarClasses[toast.severity])} />
      <span aria-hidden="true" className="mt-0.5 text-lg">
        {severityIcons[toast.severity]}
      </span>
      <div className="min-w-0 grow">
        <p className="font-bold">{toast.summary}</p>
        {toast.detail && <p className="mt-0.5 text-sm text-muted">{toast.detail}</p>}
      </div>
      <button
        type="button"
        onClick={() => onDismiss(toast.id)}
        aria-label="Dismiss notification"
        className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-overlay/10 hover:text-primary"
      >
        <FaXmark aria-hidden="true" />
      </button>
    </li>
  );
}

/**
 * Shows the toasts of the AppDataProvider (showToast). Render it once, inside the AppDataProvider and outside the pages,
 * so that toasts survive Inertia page visits.
 */
export function Toaster() {
  const { toasts, dismissToast } = useToasts();

  return (
    <section aria-label="Notifications" className="pointer-events-none fixed inset-x-0 top-4 z-60 px-4">
      {/* The live region exists before the toasts are added, so screen readers announce them */}
      <ol aria-live="polite" className="flex flex-col items-center gap-2">
        {toasts.map((toast) => (
          <ToastItem key={toast.id} toast={toast} onDismiss={dismissToast} />
        ))}
      </ol>
    </section>
  );
}
