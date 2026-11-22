import type { InvitationImport, InvitationImportOutcome } from '@knowii/common';
import { INVITATION_WORDS } from './invitation-words';

/**
 * The outcomes that the line of the counts names, in this order
 */
const ORDER: readonly InvitationImportOutcome[] = ['valid', 'queued', 'sent', 'held', 'failed', 'skipped', 'refused'];

/**
 * The counts of an import in words (#1390): "12 rows: 10 sent, 2 refused". Only the outcomes that some row has
 */
export function invitationImportCountsLine(invitationImport: InvitationImport): string {
  const parts = ORDER.filter((outcome) => invitationImport.counts[outcome] > 0).map(
    (outcome) => `${invitationImport.counts[outcome]} ${INVITATION_WORDS.outcomes[outcome]}`,
  );
  return INVITATION_WORDS.countsLine(parts, invitationImport.counts.rows);
}

/**
 * The rows whose mail ended (sent, held, failed, skipped) and the rows that were sent to the queue
 */
export function invitationImportProgress(invitationImport: InvitationImport): { ended: number; total: number } {
  const { sent, held, failed, skipped, queued } = invitationImport.counts;
  const ended = sent + held + failed + skipped;
  return { ended, total: ended + queued };
}
