import { z } from 'zod';

export const CommunitiesScalarFieldEnumSchema = z.enum(['id', 'name', 'slug', 'description', 'visibility', 'created_at', 'updated_at']);

export default CommunitiesScalarFieldEnumSchema;
