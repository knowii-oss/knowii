import { useCallback } from 'react';
import { useAppData } from '@knowii/common';
import { INVITE_LINK_WORDS } from './invite-link-words';

/**
 * Copies the address of an invite link (#1391): the toast "Link copied", or on a failure the toast "Could not copy the
 * link" with the address, so that the person copies it by hand (as the link of a comment)
 */
export function useCopyInviteLink(): (url: string) => Promise<void> {
  const { showToast } = useAppData();

  return useCallback(
    async (url: string) => {
      try {
        await navigator.clipboard.writeText(url);
        showToast({ severity: 'success', summary: INVITE_LINK_WORDS.linkCopied });
      } catch {
        showToast({ severity: 'error', summary: INVITE_LINK_WORDS.copyFailed, detail: url });
      }
    },
    [showToast],
  );
}
