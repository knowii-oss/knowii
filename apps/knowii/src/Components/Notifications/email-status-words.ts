/**
 * The words of the box of a suppressed address on the notification settings page (#1478, ADR 0054), in one place: the owner
 * approved them word for word (points 22 to 28 of "The words" of the brief). A word changes here, and nowhere else. The
 * specs read these values.
 */
export const EMAIL_STATUS_WORDS = {
  heading: (address: string): string => `We cannot reach ${address}.`,
  // After a hard bounce, 3 soft bounces or the list of the mail provider
  bounced: 'Emails to this address came back. Notification emails are paused. Emails about your account still try to reach you.',
  // After a spam report
  complaint:
    'An email from us was marked as spam. Notification emails are paused until you turn them on again. Emails about your account still try to reach you.',
  tryAgain: 'Try this address again',
  changeAddress: 'Change my address',
  // After "Try this address again"
  triedAgain: 'We will try this address again with the next email.',
  // The second try within the hour (429)
  tooSoon: 'You tried this address less than an hour ago. Try again later.',
} as const;
