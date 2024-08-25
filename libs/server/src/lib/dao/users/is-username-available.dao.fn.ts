import { PrismaClient } from '@prisma/client';
import { getLogger } from '@knowii/common';

export async function daoFnIsUsernameAvailable(username: string, prismaClient: PrismaClient): Promise<boolean> {
  const logger = getLogger('communities', daoFnIsUsernameAvailable.name);

  logger.debug('Checking if the following username is available: %s', username);
  try {
    const usersWithThatUsername = await prismaClient.users.count({
      where: {
        username,
      },
    });

    const isUserNameAvailable = usersWithThatUsername === 0;

    if (isUserNameAvailable) {
      console.log('The username is available');
    } else {
      console.log('The username is not available');
    }

    return isUserNameAvailable;
  } catch (err: unknown) {
    logger.error('Failed to check if the username is available: %o', err);
    throw new Error('Failed to check if the username is available');
  }
}
