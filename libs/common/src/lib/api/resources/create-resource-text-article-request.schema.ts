import { z } from 'zod';
import { newResourceTextArticleSchema } from '../../types/resource-text-article.schema';

export const createResourceTextArticleRequestSchema = newResourceTextArticleSchema.merge(
  z.object({
    communityCuid: z.string().cuid2(),
    resourceCollectionCuid: z.string().cuid2(),
  }),
);

export type CreateResourceTextArticleRequest = z.infer<typeof createResourceTextArticleRequestSchema>;
