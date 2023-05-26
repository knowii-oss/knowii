import { z } from 'zod';

export const ResourceCollectionsScalarFieldEnumSchema = z.enum([
  'id',
  'name',
  'slug',
  'description',
  'community_id',
  'created_at',
  'updated_at',
]);

export default ResourceCollectionsScalarFieldEnumSchema;
