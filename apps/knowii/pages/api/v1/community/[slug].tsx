import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import { generateErrorMessage } from 'zod-error';
import {
  errorInternalServerError,
  hasErrorMessage,
  errorInputValidation,
  getLogger,
  GetCommunityResponse,
  getCommunityRequestSchema,
  GetCommunityInput,
  errorCommunityNotFound,
  GetCommunityResponseData,
} from '@knowii/common';
import { daoFnGetCommunity, errorMessageOptions, getInternalUserIdFromSupabaseSession } from '@knowii/server';
import { PrismaClient } from '@prisma/client';

export default async function handler(req: NextApiRequest, res: NextApiResponse<GetCommunityResponse>) {
  const logger = getLogger('communities', req.url!);

  if (req.method !== 'GET') {
    res.setHeader('Allow', 'GET');
    res.status(405).end('Method Not Allowed');
    return;
  }

  logger.info('Handling request');

  const supabaseClient = createServerSupabaseClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    logger.warn("Anonymous user. Won't be allowed to load private communities!");
  }

  const requestValidationResult = getCommunityRequestSchema.safeParse(req.query);

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

  const { slug } = requestValidationResult.data;

  let userId = null;
  if (session) {
    userId = getInternalUserIdFromSupabaseSession(session);
  }

  const getPayload: GetCommunityInput = {
    userId,
    slug,
  };

  logger.info('Proceeding with the retrieval of: %o', getPayload);

  try {
    const prismaClient = new PrismaClient();

    const foundCommunity = await daoFnGetCommunity(getPayload, prismaClient);

    if (!foundCommunity) {
      return res.status(errorCommunityNotFound.statusCode).json({
        errors: {
          type: errorCommunityNotFound.type,
          category: errorCommunityNotFound.category,
          title: errorCommunityNotFound.code,
          titleKey: errorCommunityNotFound.key,
          errorDetails: [
            {
              detail: errorCommunityNotFound.description,
              detailKey: errorCommunityNotFound.key,
              status: errorCommunityNotFound.statusCode,
            },
          ],
        },
      });
    }

    logger.info('Found community: %o', foundCommunity);

    logger.debug('Mapping the found community to a DTO: %o', foundCommunity);

    // We do not want to return too much data, so we have to create a DTO and map just what we need
    const foundCommunityDTO: GetCommunityResponseData = {
      name: foundCommunity.name,
      description: foundCommunity.description,
    };

    const responseBody: GetCommunityResponse = {
      data: foundCommunityDTO,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while loading a community: %o', err.message);

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

    logger.warn('Error while loading a community: %o', err);

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
