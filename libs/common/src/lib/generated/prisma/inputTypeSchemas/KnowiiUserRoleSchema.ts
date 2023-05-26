import { z } from 'zod';

export const KnowiiUserRoleSchema = z.enum(['USER', 'ADMIN']);

export type KnowiiUserRoleType = `${z.infer<typeof KnowiiUserRoleSchema>}`;

export default KnowiiUserRoleSchema;
