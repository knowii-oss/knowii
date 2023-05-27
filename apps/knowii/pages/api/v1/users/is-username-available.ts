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
  const logger = getLogger(req.url!);

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

    res.status(400).json({
      error: errorInputValidation.code,
      errorDescription: errorInputValidation.description,
      errorDetails: errorMessage,
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
      isUsernameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while checking for username availability: %s', err.message);
      res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
      return;
    }

    logger.warn('Error while checking for username availability: %o', err);
    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
