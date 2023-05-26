import { z } from 'zod';

export const ResourcesScalarFieldEnumSchema = z.enum([
  'id',
  'name',
  'description',
  'content',
  'source',
  'slug',
  'up_votes',
  'down_votes',
  'community_id',
  'resource_collection_id',
  'created_at',
  'updated_at',
]);

export default ResourcesScalarFieldEnumSchema;
