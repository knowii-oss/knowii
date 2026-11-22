/**
 * The words of the screens of the invitations (#1390), in one place. Each text marked [W] in the brief is a
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
  loadMore: 'Load more',
  // "20 more loaded": the real number of new rows
  loaded: (count: number) => `${count} more loaded`,
} as const;
