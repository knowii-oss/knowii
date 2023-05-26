import { z } from 'zod';

export const KnowiiCommunityVisibilitySchema = z.enum(['PUBLIC', 'PRIVATE']);

export type KnowiiCommunityVisibilityType = `${z.infer<typeof KnowiiCommunityVisibilitySchema>}`;

export default KnowiiCommunityVisibilitySchema;
