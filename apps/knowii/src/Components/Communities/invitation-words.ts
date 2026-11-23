/**
 * The words of the screens of the invitations (#1390, ADR 0061), in one place. Each text marked [W] in the brief is a
 * proposal that the owner approves with the pictures: a word changes here, and nowhere else. The specs read these
 * values.
 */
export const INVITATION_WORDS = {
  // The picker of the access groups and the member tags of an invitation
  pickerButton: 'Access groups and tags',
  pickerButtonWithCount: (count: number) => `Access groups and tags (${count})`,
  pickerTitle: 'Access groups and tags',
  pickerDescription: 'The person gets them when they accept the invitation. The invitation does not show them.',
  pickerAccessGroups: 'Access groups',
  pickerMemberTags: 'Member tags',
  pickerDone: 'Done',
  // A tag of the managers: the hidden words after its name
  managersOnly: 'managers only',
  tagLimit: (limit: number) => `An invitation holds at most ${limit} tags`,
  // A row of "Pending invitations"
  pendingAccessGroups: (names: string) => `Access groups: ${names}`,
  // A report of the mail provider on the invitation email (#1478)
  bounceHard: 'This address could not be reached. Check it, then invite again.',
  bounceSoft: 'The email to this address did not arrive yet. Invite again later.',
  loadMore: 'Load more',
  // "20 more loaded": the real number of new rows
  loaded: (count: number) => `${count} more loaded`,

  // Invitations from a file (part B)
  importLink: 'Invite from a file',
  importTitle: 'Invite from a file',
  importSteps: ['Choose a file', 'Check', 'Send'],
  fileLabel: 'CSV file',
  roleLabel: 'Role',
  checkFile: 'Check the file',
  chooseFile: 'Choose a CSV file',
  importHelp:
    'One row per person. The column email is required. The columns role, access_groups and tags are optional. Separate several names in a cell with |. A file holds at most 1000 rows.',
  downloadTemplate: 'Download a template',
  earlierImports: 'Earlier imports',
  noImport: 'No import yet',
  // The state of an import, in words
  states: { preview: 'Not sent yet', sending: 'Sending', done: 'Done' },
  startedBy: (name: string | null) => (name === null ? 'Deleted member' : name),
  // The counts of an import in the list: "12 rows: 10 sent, 2 refused"
  countsLine: (parts: readonly string[], rows: number) =>
    `${rows === 1 ? '1 row' : `${rows} rows`}${parts.length > 0 ? `: ${parts.join(', ')}` : ''}`,
  // The words of each outcome: a text, never a color alone
  outcomes: {
    valid: 'can be invited',
    refused: 'refused',
    queued: 'waiting',
    sent: 'sent',
    held: 'held',
    failed: 'failed',
    skipped: 'skipped',
  },
  // The preview
  summary: (valid: number, refused: number) =>
    `${valid === 1 ? '1 row can be invited.' : `${valid} rows can be invited.`} ${refused === 1 ? '1 row is refused.' : `${refused} rows are refused.`}`,
  ignoredColumns: (names: readonly string[]) => `Not used: ${names.join(', ')}`,
  refusedRows: 'Refused rows',
  rowsToInvite: 'Rows to invite',
  rowLabel: (rowNumber: number) => `Row ${rowNumber}`,
  send: (count: number) => (count === 1 ? 'Send 1 invitation' : `Send ${count} invitations`),
  discard: 'Discard',
  discardQuestion: 'Discard this import? Nothing is sent.',
  cancel: 'Cancel',
  noValidRow: 'No row can be invited. Fix the file, then upload it again.',
  // The send
  sendingNote: 'You can leave this page. The invitations go out at the pace that the mail provider accepts.',
  progress: (ended: number, total: number) => `${ended} of ${total} mails done`,
  // The log
  notSent: 'Not sent',
  refused: 'Refused',
  sent: 'Sent',
  sendNotSent: 'Send the mails that were not sent',
  inviteAgain: 'Invite again the people who did not join',
  inviteAgainFrom: (date: string) => `You can invite the people who did not join again from ${date}.`,
  invitedAgainOn: (date: string) => `You invited the people who did not join again on ${date}.`,
  deleteRecord: 'Delete this record',
  deleteQuestion: 'Delete this record? The invitations that it sent stay.',
  delete: 'Delete',
  keptUntil: (date: string) => `This record is deleted on ${date}.`,
  previewKeptUntil: (date: string) => `This check is kept until ${date}.`,
  noRows: 'No row',
  stillInvited: 'invitation pending',
} as const;
