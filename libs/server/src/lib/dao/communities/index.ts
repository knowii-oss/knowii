import { Communities, PrismaClient } from '@prisma/client';
import { CreateCommunityInput, createCommunityInputSchema, errorInputValidation, getLogger } from '@knowii/common';
import { generateErrorMessage } from 'zod-error';
import { errorMessageOptions } from '../../utils';

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

export async function daoFnCreateCommunity(input: CreateCommunityInput, prismaClient: PrismaClient): Promise<Communities> {
  const logger = getLogger('communities', daoFnCreateCommunity.name);

  logger.info('Creating a new community: %s', input);

  const inputValidationResult = createCommunityInputSchema.safeParse(input);

  if (!inputValidationResult.success) {
    const errorMessage = generateErrorMessage(inputValidationResult.error.issues, errorMessageOptions);
    logger.warn(`${errorInputValidation.description}. Error(s) detected: %s`, errorMessage);

    // FIXME introduce specific error types
    throw new Error(errorMessage);
  }

  try {
    const createdCommunity = await prismaClient.communities.create({
      data: {
        name: input.name,
        description: input.description,
        slug: input.slug,
      },
    });

    logger.info('Community created successfully: %o', createdCommunity);

    return createdCommunity;
  } catch (err: unknown) {
    logger.error('Failed to create the community: %o', err);
    // FIXME improve error handling
    throw err;
  }
}
