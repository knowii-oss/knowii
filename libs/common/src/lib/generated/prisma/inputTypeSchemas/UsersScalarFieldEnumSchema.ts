import { z } from 'zod';

export const UsersScalarFieldEnumSchema = z.enum(['id', 'user_id_external', 'username', 'email', 'user_role', 'created_at', 'updated_at']);

export default UsersScalarFieldEnumSchema;
