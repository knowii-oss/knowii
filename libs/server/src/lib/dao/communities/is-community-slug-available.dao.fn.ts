import { PrismaClient } from '@prisma/client';
import { getLogger } from '@knowii/common';

export async function daoFnIsCommunitySlugAvailable(slug: string, prismaClient: PrismaClient): Promise<boolean> {
  const logger = getLogger('communities', daoFnIsCommunitySlugAvailable.name);

  logger.debug('Checking if the following community slug is available: %s', name);
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
}
