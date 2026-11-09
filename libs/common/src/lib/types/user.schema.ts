import { z } from 'zod';
import { SOCIAL_MEDIA_LINK_PROPERTIES, socialMediaLinksSchema } from './social-media-link.schema';

export const currentUserSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  email: z.email(),
  username: z.string(),
  two_factor_enabled: z.boolean(),
  two_factor_confirmed_at: z.coerce.date().nullable(),
  email_verified_at: z.coerce.date().nullable(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type CurrentUser = z.infer<typeof currentUserSchema>;

export const userProfileSchema = z
  .object({
    name: z.string(),
    username: z.string().nullable(),
    bio: z.string().nullable(),
    location: z.string().nullable(),
    phone: z.string().nullable(),
    // What the other members see (#1076). The shared Inertia prop holds the stored choices (null: the defaults)
    field_visibility: z.record(z.string(), z.boolean()).nullable().optional(),
  })
  .extend(socialMediaLinksSchema.shape);

export type UserProfile = z.infer<typeof userProfileSchema>;

/**
 * The profile fields that a member shows to or hides from the other members (#1076, ADR 0020).
 * WARNING: aligned with ProfileFieldVisibility.php
 */
export const PROFILE_VISIBILITY_FIELDS = ['bio', 'location', 'phone', ...SOCIAL_MEDIA_LINK_PROPERTIES] as const;
export type ProfileVisibilityField = (typeof PROFILE_VISIBILITY_FIELDS)[number];
export type ProfileFieldVisibility = Record<ProfileVisibilityField, boolean>;

/**
 * Every field with the stored choice, or its default: shown, except the phone.
 */
export const resolveProfileFieldVisibility = (stored: Record<string, boolean> | null | undefined): ProfileFieldVisibility => {
  const visibility = Object.fromEntries(PROFILE_VISIBILITY_FIELDS.map((field) => [field, field !== 'phone'])) as ProfileFieldVisibility;
  for (const field of PROFILE_VISIBILITY_FIELDS) {
    const value = stored?.[field];
    if (typeof value === 'boolean') {
      visibility[field] = value;
    }
  }
  return visibility;
};
