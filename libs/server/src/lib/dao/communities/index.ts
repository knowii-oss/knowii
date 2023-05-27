import { Communities, PrismaClient } from '@prisma/client';
import { CreateCommunityInput, createCommunityInputSchema, errorInputValidation } from '@knowii/common';
import { generateErrorMessage } from 'zod-error';
import { errorMessageOptions } from '../../utils';

export async function daoFnIsCommunityNameAvailable(name: string, prismaClient: PrismaClient): Promise<boolean> {
  const communitiesWithThatName = await prismaClient.communities.count({
    where: {
      name,
    },
  });

  const isNameAvailable = communitiesWithThatName === 0;

  if (isNameAvailable) {
    console.log('The community name is available');
  } else {
    console.log('The community name is not available');
  }

  return isNameAvailable;
}

export async function daoFnIsCommunitySlugAvailable(slug: string, prismaClient: PrismaClient): Promise<boolean> {
  const communitiesWithThatSlug = await prismaClient.communities.count({
    where: {
      slug,
    },
  });

  const isSlugAvailable = communitiesWithThatSlug === 0;

  if (isSlugAvailable) {
    console.log('The community slug is available');
  } else {
    console.log('The community slug is not available');
  }

  return isSlugAvailable;
}

export async function daoFnCreateCommunity(input: CreateCommunityInput, prismaClient: PrismaClient): Promise<Communities> {
  console.log('Creating a new community: ', input);

  const inputValidationResult = createCommunityInputSchema.safeParse(input);

  if (!inputValidationResult.success) {
    const errorMessage = generateErrorMessage(inputValidationResult.error.issues, errorMessageOptions);
    console.warn(`${errorInputValidation.description}. Error(s) detected: `, errorMessage);

    // FIXME introduce specific error types
    throw new Error(errorMessage);
  }

  const createdCommunity = await prismaClient.communities.create({
    data: {
      name: input.name,
      description: input.description,
      slug: input.slug,
    },
  });

  return createdCommunity;
}
