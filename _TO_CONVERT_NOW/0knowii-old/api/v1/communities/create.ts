import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import { generateErrorMessage } from 'zod-error';
import {
  Communities,
  CreateCommunityInput,
  createCommunityRequestSchema,
  CreateCommunityResponse,
  errorClientNotAuthenticated,
  errorCommunityNameNotAvailable,
  errorCommunitySlugNotAvailable,
  errorInputValidation,
  errorInternalServerError,
  generateSlug,
  getLogger,
  hasErrorMessage,
} from '@knowii/common';
import {
  daoFnCreateCommunity,
  daoFnIsCommunityNameAvailable,
  daoFnIsCommunitySlugAvailable,
  errorMessageOptions,
  getInternalUserIdFromSupabaseSession,
} from '@knowii/server';
import { PrismaClient } from '@prisma/client';

export default async function handler(req: NextApiRequest, res: NextApiResponse<CreateCommunityResponse>) {
  const logger = getLogger('communities', req.url!);

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  logger.info('Handling request');

  const supabaseClient = createServerSupabaseClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    return res.status(errorClientNotAuthenticated.statusCode).json({
      errors: {
        type: errorClientNotAuthenticated.type,
        category: errorClientNotAuthenticated.category,
        title: errorClientNotAuthenticated.code,
        titleKey: errorClientNotAuthenticated.key,
        errorDetails: [
          {
            detail: errorClientNotAuthenticated.description,
            detailKey: errorClientNotAuthenticated.key,
            status: errorClientNotAuthenticated.statusCode,
          },
        ],
      },
    });
  }

  const requestValidationResult = createCommunityRequestSchema.safeParse(req.body);

  if (!requestValidationResult.success) {
    const errorMessage = generateErrorMessage(requestValidationResult.error.issues, errorMessageOptions);
    logger.warn(`${errorInputValidation.description}. Error(s) detected: %s`, errorMessage);

    res.status(errorInputValidation.statusCode).json({
      errors: {
        type: errorInputValidation.type,
        category: errorInputValidation.category,
        title: errorInputValidation.code,
        titleKey: errorInputValidation.key,
        errorDetails: [
          {
            detail: errorInputValidation.description,
            detailKey: errorInputValidation.key,
            status: errorInputValidation.statusCode,
          },
        ],
      },
    });
    return;
  }

  logger.info('Request validated. Data: %o', requestValidationResult.data);

  const { name, description } = requestValidationResult.data;
  const slug = generateSlug(name);
  const ownerUserId = getInternalUserIdFromSupabaseSession(session);

  const creationPayload: CreateCommunityInput = {
    ownerUserId,
    name,
    description,
    slug,
  };

  try {
    const prismaClient = new PrismaClient();

    logger.info('Verifying if the community name is available');
    const isCommunityNameAvailable = await daoFnIsCommunityNameAvailable(creationPayload.name, prismaClient);

    if (!isCommunityNameAvailable) {
      logger.info('The community name is not available');
      return res.status(errorCommunityNameNotAvailable.statusCode).json({
        errors: {
          type: errorCommunityNameNotAvailable.type,
          category: errorCommunityNameNotAvailable.category,
          title: errorCommunityNameNotAvailable.code,
          titleKey: errorCommunityNameNotAvailable.key,
          errorDetails: [
            {
              detail: errorCommunityNameNotAvailable.description,
              detailKey: errorCommunityNameNotAvailable.key,
              status: errorCommunityNameNotAvailable.statusCode,
            },
          ],
        },
      });
    }

    logger.info('Verifying if the community slug is available');
    const isCommunitySlugAvailable = await daoFnIsCommunitySlugAvailable(creationPayload.slug, prismaClient);

    if (!isCommunitySlugAvailable) {
      logger.info('The community slug is not available');
      return res.status(errorCommunitySlugNotAvailable.statusCode).json({
        errors: {
          type: errorCommunitySlugNotAvailable.type,
          category: errorCommunitySlugNotAvailable.category,
          title: errorCommunitySlugNotAvailable.code,
          titleKey: errorCommunitySlugNotAvailable.key,
          errorDetails: [
            {
              detail: errorCommunitySlugNotAvailable.description,
              detailKey: errorCommunitySlugNotAvailable.key,
              status: errorCommunitySlugNotAvailable.statusCode,
            },
          ],
        },
      });
    }

    logger.info('Proceeding with the creation: %o', creationPayload);

    const createdCommunity = <Communities>await daoFnCreateCommunity(creationPayload, prismaClient);

    logger.info('Created community: %o', createdCommunity);

    const responseBody: CreateCommunityResponse = {
      data: {
        name: createdCommunity.name,
        description: createdCommunity.description,
        slug: createdCommunity.slug,
      },
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while creating a new community: %o', err.message);

      return res.status(errorInternalServerError.statusCode).json({
        errors: {
          type: errorInternalServerError.type,
          category: errorInternalServerError.category,
          title: errorInternalServerError.code,
          titleKey: errorInternalServerError.key,
          errorDetails: [
            {
              detail: errorInternalServerError.description,
              detailKey: errorInternalServerError.key,
              status: errorInternalServerError.statusCode,
            },
          ],
        },
      });
    }

    logger.warn('Error while creating a new community: %o', err);

    res.status(errorInternalServerError.statusCode).json({
      errors: {
        type: errorInternalServerError.type,
        category: errorInternalServerError.category,
        title: errorInternalServerError.code,
        titleKey: errorInternalServerError.key,
        errorDetails: [
          {
            detail: errorInternalServerError.description,
            detailKey: errorInternalServerError.key,
            status: errorInternalServerError.statusCode,
          },
        ],
      },
    });
  }
}
