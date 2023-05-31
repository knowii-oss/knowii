import { PrismaClient } from '@prisma/client';
import { getLogger } from '@knowii/common';

export async function daoFnIsCommunityNameAvailable(name: string, prismaClient: PrismaClient): Promise<boolean> {
  const logger = getLogger('communities', daoFnIsCommunityNameAvailable.name);

  logger.debug('Checking if the following community name is available: %s', name);
  const communitiesWithThatName = await prismaClient.communities.count({
    where: {
      name,
    },
  });

  const isNameAvailable = communitiesWithThatName === 0;

  if (isNameAvailable) {
    logger.debug('The community name is available');
  } else {
    logger.debug('The community name is not available');
  }

  return isNameAvailable;
}
