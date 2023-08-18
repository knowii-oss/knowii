import { NextApiRequest, NextApiResponse } from 'next';
import {
  cleanUsername,
  errorInputValidation,
  errorInternalServerError,
  getLogger,
  hasErrorMessage,
  isUsernameAvailableRequestSchema,
  IsUsernameAvailableResponse,
} from '@knowii/common';
import { daoFnIsUsernameAvailable, errorMessageOptions } from '@knowii/server';
import { PrismaClient } from '@prisma/client';
import { generateErrorMessage } from 'zod-error';

export default async function handler(req: NextApiRequest, res: NextApiResponse<IsUsernameAvailableResponse>) {
  const logger = getLogger('users', req.url!);

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  logger.info('Handling request');

  const requestValidationResult = isUsernameAvailableRequestSchema.safeParse(req.body);

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

  let { usernameToCheck } = requestValidationResult.data;
  usernameToCheck = cleanUsername(usernameToCheck);

  try {
    // WARNING: The argument name MUST match the exact argument name of the function declared in supabase-db-seed.sql
    const prismaClient = new PrismaClient();

    const isUsernameAvailable = await daoFnIsUsernameAvailable(usernameToCheck, prismaClient);

    logger.info('Username is %s', isUsernameAvailable ? 'available' : 'not available');

    const responseBody: IsUsernameAvailableResponse = {
      data: {
        isUsernameAvailable,
      },
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while checking for username availability: %s', err.message);
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

    logger.warn('Error while checking for username availability: %o', err);
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
