import type { NotificationEmailGroup } from '@knowii/common';

/**
 * The words of the stop page of a notification email (#1478, ADR 0054), in one place: the owner approved them word for word
 * (points 1 to 16 of "The words" of the brief). A word changes here, and nowhere else. The specs read these values. The page
 * names no person, no address and no community: the link of an email can be forwarded.
 */

// The groups, as they show in a sentence and on a button
export const EMAIL_GROUP_NAMES = {
  replies_and_mentions: 'replies and mentions',
  direct_messages: 'direct messages',
  important_news: 'important news',
  weekly_summary: 'the weekly summary',
  events: 'events',
} as const satisfies Record<NotificationEmailGroup, string>;

const isSummary = (group: NotificationEmailGroup): boolean => group === 'weekly_summary';

export const MAIL_STOP_WORDS = {
  // The confirm state: the browser title and the heading
  confirmTitle: (group: NotificationEmailGroup): string =>
    isSummary(group) ? 'Stop the weekly summary?' : `Stop emails about ${EMAIL_GROUP_NAMES[group]}?`,
  confirmText: 'Press the button to stop these emails. Nothing changes before you press it.',
  confirmButton: (group: NotificationEmailGroup): string =>
    isSummary(group) ? 'Stop the weekly summary' : `Stop emails about ${EMAIL_GROUP_NAMES[group]}`,

  // The done state (also a GET when the group is already off)
  doneTitle: (group: NotificationEmailGroup): string =>
    isSummary(group)
      ? 'You will not get the weekly summary any more.'
      : `You will not get emails about ${EMAIL_GROUP_NAMES[group]} any more.`,
  doneEveryCommunity: 'This counts for every community that you are in.',
  doneStillArrives: 'You still get sign-in links and the emails about your account. You still get the other emails that you chose.',
  undo: 'Undo',
  emailSettings: 'Email settings',

  // The undone state
  undoneTitle: (group: NotificationEmailGroup): string => `Emails about ${EMAIL_GROUP_NAMES[group]} are on again.`,
  undoneText: 'You get them as before.',

  // A link that is not valid
  invalidTitle: 'This link is not valid.',
  invalidText: 'Sign in and open your email settings.',

  // A failed POST of the page
  failed: 'We could not save this. Try again.',
} as const;
