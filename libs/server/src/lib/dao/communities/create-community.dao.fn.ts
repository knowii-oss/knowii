import { CreateCommunityInput, createCommunityInputSchema, errorInputValidation, getLogger } from '@knowii/common';
import { Communities, PrismaClient } from '@prisma/client';
import { generateErrorMessage } from 'zod-error';
import { errorMessageOptions } from '@knowii/server';

export async function daoFnCreateCommunity(input: CreateCommunityInput, prismaClient: PrismaClient): Promise<Communities> {
  const logger = getLogger('communities', daoFnCreateCommunity.name);

  logger.info('Creating a new community: %o', input);

  const inputValidationResult = createCommunityInputSchema.safeParse(input);

  if (!inputValidationResult.success) {
    const errorMessage = generateErrorMessage(inputValidationResult.error.issues, errorMessageOptions);
    logger.warn(`${errorInputValidation.description}. Error(s) detected: %s`, errorMessage);

    // TODO introduce specific error types
    throw new Error(errorMessage);
  }

  try {
    const createdCommunity = await prismaClient.communities.create({
      data: {
        name: input.name,
        description: input.description,
        slug: input.slug,
        owners: {
          connect: {
            id: input.ownerUserId,
          },
        },
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
