import { z } from 'zod';

/**
 * The notification email groups (#1478, ADR 0054): what "Stop emails like this" stops.
 * WARNING: aligned with App\Enums\KnowiiNotificationEmailGroup
 */
export const NOTIFICATION_EMAIL_GROUPS = ['replies_and_mentions', 'direct_messages', 'important_news', 'weekly_summary', 'events'] as const;
export const notificationEmailGroupSchema = z.enum(NOTIFICATION_EMAIL_GROUPS);
export type NotificationEmailGroup = z.infer<typeof notificationEmailGroupSchema>;

/**
 * Why the notification emails of an address stopped (#1478).
 * WARNING: aligned with App\Enums\KnowiiEmailSuppressionReason
 */
export const EMAIL_SUPPRESSION_REASONS = ['hard_bounce', 'complaint', 'soft_bounces', 'provider_list'] as const;
export const emailSuppressionReasonSchema = z.enum(EMAIL_SUPPRESSION_REASONS);
export type EmailSuppressionReason = z.infer<typeof emailSuppressionReasonSchema>;

/**
 * The email status of the current member (#1478): the suppression of the address and the state of each group. A group
 * without a choice is `default`; slice 4b writes only `off`, through the stop link of an email.
 * WARNING: aligned with App\Http\Resources\EmailStatusResource
 */
export const emailStatusSchema = z.object({
  suppressed: z.boolean(),
  reason: emailSuppressionReasonSchema.nullable(),
  since: z.string().nullable(),
  address_hint: z.string(),
  email_choices: z.array(z.object({ group: notificationEmailGroupSchema, state: z.string() })),
});
export type EmailStatus = z.infer<typeof emailStatusSchema>;
