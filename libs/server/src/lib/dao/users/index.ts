import { PrismaClient } from '@prisma/client';

export async function daoFnIsUsernameAvailable(username: string, prismaClient: PrismaClient): Promise<boolean> {
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
}
