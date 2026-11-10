import { FaCheck } from 'react-icons/fa6';
import type { CommunityNameAvailability } from './use-community-name-availability';

interface CommunityNameStatusProps {
  /**
   * Lets the name field reference the status with aria-describedby
   */
  id: string;
  status: CommunityNameAvailability;
}

/**
 * The answer of the name check under a community name field (#1136). The region always exists, so screen readers
 * announce each new answer.
 */
export function CommunityNameStatus({ id, status }: CommunityNameStatusProps) {
  return (
    <p id={id} aria-live="polite" className="mt-1 text-xs">
      {status === 'checking' && <span className="text-muted">Checking the name…</span>}
      {status === 'available' && (
        <span className="inline-flex items-center gap-1 text-muted">
          <FaCheck aria-hidden="true" className="text-success" />
          This name is available.
        </span>
      )}
      {status === 'taken' && <span className="text-danger-text">This name is already taken. Choose another one.</span>}
    </p>
  );
}
