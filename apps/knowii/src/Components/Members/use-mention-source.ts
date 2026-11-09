import { useMemo } from 'react';
import { knowiiApiClient, MENTION_SUGGESTIONS_LIMIT } from '@knowii/common';
import type { MentionSource } from '@/Components/ui';

/**
 * Suggestions for "@" in the Markdown editor: members of the community (#1177).
 */
export function useMentionSource(communityCuid: string): MentionSource {
  return useMemo(
    () => ({
      search: (query: string, signal: AbortSignal) =>
        knowiiApiClient.communities.members.search(communityCuid, query, MENTION_SUGGESTIONS_LIMIT, signal),
    }),
    [communityCuid],
  );
}
