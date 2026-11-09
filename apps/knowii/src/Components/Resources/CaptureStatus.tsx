import type { CaptureStatus as CaptureStatusValue } from '@knowii/common';
import { FaTriangleExclamation } from 'react-icons/fa6';
import { cn } from '@/Components/ui';

interface Props {
  status: CaptureStatusValue;
  className?: string;
}

/**
 * Where the capture of a resource is (ADR 0018): nothing once it is captured, "Capturing" while the queued job runs,
 * "Capture failed" after the last try. A status region, so screen readers hear the change.
 */
export function CaptureStatus({ status, className }: Props) {
  return (
    <span role="status" className={cn('inline-flex items-center gap-1 text-xs', className)}>
      {(status === 'pending' || status === 'capturing') && (
        <>
          <span
            aria-hidden="true"
            className="size-3 animate-spin rounded-full border-2 border-overlay/20 border-t-secondary motion-reduce:animate-none"
          />
          <span className="text-muted">Capturing the page…</span>
        </>
      )}
      {status === 'failed' && (
        <>
          <FaTriangleExclamation aria-hidden="true" className="text-warning" />
          <span className="text-muted">Capture failed</span>
        </>
      )}
    </span>
  );
}
