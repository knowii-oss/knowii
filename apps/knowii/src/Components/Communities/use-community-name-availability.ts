import { useEffect, useState } from 'react';
import { knowiiApiClient, newCommunitySchema, useDebounce } from '@knowii/common';

/**
 * - `idle`: nothing to check (the check is off, or the name breaks the rules that the form shows already)
 * - `checking`: the user is typing, or the server did not answer yet
 * - `available` / `taken`: the answer of the server for the current name
 * - `unknown`: the check failed (network, rate limit): the server checks again when the form is sent
 */
export type CommunityNameAvailability = 'idle' | 'checking' | 'available' | 'taken' | 'unknown';

export const COMMUNITY_NAME_CHECK_DELAY = 300;

const nameSchema = newCommunitySchema.shape.name;

interface Answer {
  name: string;
  status: Exclude<CommunityNameAvailability, 'idle' | 'checking'>;
}

/**
 * Checks whether a shared community can take a name while the user types (#1136, ADR 0033): one request after
 * each pause in the typing, and a newer name cancels the request of the older one.
 *
 * @param enabled false for a personal community (personal communities never take a name) or an unchanged name
 * @param community the cuid of the community that is renamed (its own name stays available to it)
 */
export function useCommunityNameAvailability(name: string, enabled: boolean, community?: string): CommunityNameAvailability {
  const trimmed = name.trim();
  const checkable = enabled && nameSchema.safeParse(trimmed).success;
  const debounced = useDebounce(checkable ? trimmed : '', COMMUNITY_NAME_CHECK_DELAY);
  const [answer, setAnswer] = useState<Answer | null>(null);

  useEffect(() => {
    if (debounced === '') {
      return;
    }
    const controller = new AbortController();
    void knowiiApiClient.communities
      .isNameAvailable(community === undefined ? { name: debounced } : { name: debounced, community }, controller.signal)
      .then((result) => {
        if (controller.signal.aborted) {
          return;
        }
        setAnswer({ name: debounced, status: result.ok ? (result.data.available ? 'available' : 'taken') : 'unknown' });
      });
    return () => controller.abort();
  }, [debounced, community]);

  if (!checkable) {
    return 'idle';
  }
  // The answer belongs to the name that the field shows now, else a new answer is on its way
  return answer !== null && answer.name === trimmed && debounced === trimmed ? answer.status : 'checking';
}
