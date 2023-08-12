import { errorInputValidation, GetCommunityInput, getCommunityInputSchema, GetCommunityResponseData, getLogger } from '@knowii/common';
import { Communities, PrismaClient } from '@prisma/client';
import { generateErrorMessage } from 'zod-error';
import { errorMessageOptions } from '@knowii/server';

export async function daoFnGetCommunity(input: GetCommunityInput, prismaClient: PrismaClient): Promise<GetCommunityResponseData | null> {
  const logger = getLogger('communities', daoFnGetCommunity.name);

  logger.info('Loading a community: %o', input);

  const inputValidationResult = getCommunityInputSchema.safeParse(input);

  if (!inputValidationResult.success) {
    const errorMessage = generateErrorMessage(inputValidationResult.error.issues, errorMessageOptions);
    logger.warn(`${errorInputValidation.description}. Error(s) detected: %s`, errorMessage);

    // TODO introduce specific error types
    throw new Error(errorMessage);
  }

  let foundCommunity: Communities | null = null;

  try {
    // TODO prevent anonymous users (those with !session) to load private communities (cfr #480, #248, #319)
    // WARNING: the returned type is actually not Communities but a specific Prisma type that includes relationships
    // We cast the result for simplicity
    foundCommunity = await prismaClient.communities.findUnique({
      where: {
        slug: input.slug,
      },
    });
  } catch (err: unknown) {
    logger.error('Failed to load the community: %o', err);
    throw new Error('Failed to load the community');
  }

  if (!foundCommunity) {
    return null;
  }

  logger.info('Community loaded successfully: %o. Creating a DTO', foundCommunity);

  // We do not want to return too much data, so we have to create a DTO and map just what we need
  const retVal: GetCommunityResponseData = {
    name: foundCommunity.name,
    description: foundCommunity.description,
  };

  return retVal;
}
