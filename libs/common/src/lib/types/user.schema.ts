import { z } from 'zod';
import { socialMediaLinksSchema } from './social-media-link.schema';

export const currentUserSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  email: z.string().email(),
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
  })
  .merge(socialMediaLinksSchema);

export type UserProfile = z.infer<typeof userProfileSchema>;
