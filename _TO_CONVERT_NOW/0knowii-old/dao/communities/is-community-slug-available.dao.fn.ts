import { PrismaClient } from '@prisma/client';
import { getLogger } from '@knowii/common';

export async function daoFnIsCommunitySlugAvailable(slug: string, prismaClient: PrismaClient): Promise<boolean> {
  const logger = getLogger('communities', daoFnIsCommunitySlugAvailable.name);

  logger.debug('Checking if the following community slug is available: %s', slug);

  try {
    const communitiesWithThatSlug = await prismaClient.communities.count({
      where: {
        slug,
      },
    });

    const isSlugAvailable = communitiesWithThatSlug === 0;

    if (isSlugAvailable) {
      logger.debug('The community slug is available');
    } else {
      logger.debug('The community slug is not available');
    }

    return isSlugAvailable;
  } catch (err: unknown) {
    logger.error('Failed to check if the community slug is available: %o', err);
    throw new Error('Failed to check if the community slug is available');
  }
}
