import { z } from 'zod';
import { newTextResourceSchema } from '../../types/text-resource.schema';

export const createResourceTextArticleRequestSchema = newTextResourceSchema.merge(
  z.object({
    communityCuid: z.string().cuid2(),
    resourceCollectionCuid: z.string().cuid2(),
  }),
);

export type CreateResourceTextArticleRequest = z.infer<typeof createResourceTextArticleRequestSchema>;
