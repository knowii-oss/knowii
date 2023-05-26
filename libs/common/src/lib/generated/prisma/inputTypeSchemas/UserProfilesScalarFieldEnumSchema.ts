import { z } from 'zod';

export const UserProfilesScalarFieldEnumSchema = z.enum([
  'id',
  'user_id',
  'user_id_external',
  'name',
  'avatar_url',
  'phone',
  'website',
  'twitter',
  'facebook',
  'instagram',
  'tiktok',
  'github',
  'bio',
  'location',
  'organization_name',
  'organization_link',
  'created_at',
  'updated_at',
]);

export default UserProfilesScalarFieldEnumSchema;
