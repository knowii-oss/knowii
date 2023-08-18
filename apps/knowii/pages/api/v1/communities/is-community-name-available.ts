import { NextApiRequest, NextApiResponse } from 'next';
import {
  errorClientNotAuthenticated,
  errorInternalServerError,
  hasErrorMessage,
  IsCommunityNameAvailableResponse,
  cleanCommunityName,
  getLogger,
  isCommunityNameAvailableRequestSchema,
  errorInputValidation,
} from '@knowii/common';
import { PrismaClient } from '@prisma/client';
import { daoFnIsCommunityNameAvailable, errorMessageOptions } from '@knowii/server';
import { generateErrorMessage } from 'zod-error';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';

export default async function handler(req: NextApiRequest, res: NextApiResponse<IsCommunityNameAvailableResponse>) {
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

  const requestValidationResult = isCommunityNameAvailableRequestSchema.safeParse(req.body);

  if (!requestValidationResult.success) {
    const errorMessage = generateErrorMessage(requestValidationResult.error.issues, errorMessageOptions);
    logger.warn(`${errorInputValidation.description}. Error(s) detected: %s`, errorMessage);

    res.status(errorInputValidation.statusCode).json({
      errors: {
        type: errorInputValidation.type,
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

  let { nameToCheck } = requestValidationResult.data;
  nameToCheck = cleanCommunityName(nameToCheck);

  try {
    const prismaClient = new PrismaClient();

    const isNameAvailable = await daoFnIsCommunityNameAvailable(nameToCheck, prismaClient);

    logger.info('Community name is %s', isNameAvailable ? 'available' : 'not available');

    const responseBody: IsCommunityNameAvailableResponse = {
      data: {
        isNameAvailable,
      },
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while checking for community name availability: %s', err.message);
      res.status(errorInternalServerError.statusCode).json({
        errors: {
          type: errorInternalServerError.type,
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
      return;
    }

    logger.warn('Error while checking for community name availability: %o', err);
    res.status(errorInternalServerError.statusCode).json({
      errors: {
        type: errorInternalServerError.type,
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
