import type { InviteLinkStatus } from '@knowii/common';

/**
 * The words of the screens of the invite links (#1391, ADR 0062), in one place. Each text marked [W] in the brief is a
 * proposal that the owner approves with the pictures: a word changes here, and nowhere else. The specs read these
 * values. The screens say "invite link", never "invitation link" (the links of the invitation mails).
 */
export const INVITE_LINK_WORDS = {
  // The section of the settings
  sectionTitle: 'Invite links',
  sectionDescription:
    'An address that you share on a sales page, in a newsletter or in a course. Anyone who opens it can join this community as a member, with the access groups and the tags of the link.',
  newLink: 'New invite link',
  emptyTitle: 'No invite link yet',
  emptyDescription: 'Create a link, then copy it and share it.',
  listLabel: 'Invite links',

  // A row of the list
  status: { active: 'Active', off: 'Off', ended: 'Ended', full: 'Full' } satisfies Record<InviteLinkStatus, string>,
  joined: (count: number) => `${count} joined`,
  joinedOfMax: (count: number, max: number) => `${count} of ${max} joined`,
  ends: 'Ends',
  gives: 'Gives',
  givesNothing: 'Gives no access group and no tag',
  fedByRule: (group: string) => `${group}: a rule on an entitlement feeds this group, so this link does not give it.`,
  copyLink: 'Copy link',
  copyLinkOf: (name: string) => `Copy the link ${name}`,
  linkCopied: 'Link copied',
  copyFailed: 'Could not copy the link',
  on: 'On',
  // Read before "On" by a screen reader: the switch names its link ("The link Course buyers: On")
  onHidden: (name: string) => `The link ${name}:`,
  savedOn: 'Saved: on',
  savedOff: 'Saved: off',
  edit: 'Edit',
  editOf: (name: string) => `Edit the link ${name}`,
  whoJoined: 'Who joined',
  whoJoinedOf: (name: string) => `Who joined through the link ${name}`,
  delete: 'Delete',
  deleteOf: (name: string) => `Delete the link ${name}`,

  // The dialog of a new link and of a change
  newTitle: 'New invite link',
  editTitle: (name: string) => `Edit the invite link "${name}"`,
  nameLabel: 'Name',
  nameHint: 'Only the managers see the name.',
  namePlaceholder: 'Course buyers',
  groupsAndTags: 'Access groups and tags',
  pickerDescription: 'A person gets them when they join through the link. The page of the link does not show them.',
  tagLimit: (limit: number) => `An invite link holds at most ${limit} tags`,
  fedByRuleShort: 'A rule on an entitlement feeds this group: a link cannot give it.',
  endsLabel: 'Ends',
  endsDate: 'Date',
  endsTime: 'Time',
  endsHint: (zone: string) => `Optional. In your time zone: ${zone}.`,
  endsNeedsBoth: 'Enter a date and a time, or neither',
  endsInvalid: 'This time does not exist in your time zone. Choose another one',
  maxLabel: 'Maximum number of joins',
  maxHint: 'Optional.',
  warning:
    'Anyone who has this link can join this community and gets these groups and tags. Turn the link off, or set an end or a maximum, when you share it widely.',
  cancel: 'Cancel',
  create: 'Create the link',
  save: 'Save',
  created: 'Invite link created',
  createdDescription: 'Copy the link and share it. You can copy it again from the list at any time.',
  addressLabel: 'Address of the link',
  done: 'Done',
  saved: (name: string) => `Invite link "${name}" saved`,

  // The question of a delete: the title, then the details by the number of joins (the count holds the people who left)
  deleteTitle: (name: string) => `Delete the invite link "${name}"?`,
  deleteDetails: (count: number) => {
    if (count === 0) {
      return 'It stops working at once. Nobody joined through it. This cannot be undone.';
    }
    if (count === 1) {
      return 'It stops working at once. 1 person joined through it: if they are still a member, they keep their groups and tags. The list of who joined goes. This cannot be undone.';
    }
    return `It stops working at once. ${count} people joined through it: those who are still members keep their groups and tags. The list of who joined goes. This cannot be undone.`;
  },
  deleted: (name: string) => `Invite link "${name}" deleted`,

  // "Who joined"
  whoJoinedTitle: (name: string) => `Who joined through "${name}"`,
  whoJoinedEmpty: 'Nobody joined through this link, or they left.',
  loadMore: 'Load more',
  loaded: (count: number) => `${count} more loaded`,
  close: 'Done',

  // The page of a link
  pageTitle: (community: string) => `Join ${community}`,
  signUpToJoin: 'Sign up to join',
  signIn: 'Sign in',
  visitorHint: 'After you confirm your email address, open this link again if you do not come back to this page.',
  join: 'Join',
  joining: 'Joining',
  welcome: (community: string) => `Welcome to ${community}`,
  alreadyMember: 'You are already a member of this community.',
  openCommunity: 'Open the community',
  blocked: 'You cannot join this community by yourself. Ask its managers for an invitation.',
  unusableTitle: 'This invite link does not work',
  // WARNING: aligned with InviteLinkTexts::UNUSABLE (the answer of the API)
  unusableMessage: 'This invite link does not work. Ask the person who gave it to you for a new one.',
} as const;
