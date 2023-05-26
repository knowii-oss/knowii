import { z } from 'zod';

export const TagsScalarFieldEnumSchema = z.enum(['id', 'name', 'community_id', 'created_at', 'updated_at']);

export default TagsScalarFieldEnumSchema;
