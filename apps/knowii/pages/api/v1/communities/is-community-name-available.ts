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
import { daoFnIsCommunityNameAvailable, errorMessageOptions, NextRequestHandler } from '@knowii/server';
import { generateErrorMessage } from 'zod-error';

const handler: NextRequestHandler<IsCommunityNameAvailableResponse> = async (
  req: NextApiRequest,
  res: NextApiResponse<IsCommunityNameAvailableResponse>,
) => {
  const logger = getLogger('communities', req.url!);

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  logger.info('Handling request');

  const supabaseClient = createPagesServerClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    return res.status(401).json({
      error: errorClientNotAuthenticated.code,
      errorDescription: errorClientNotAuthenticated.description,
    });
  }

  const requestValidationResult = isCommunityNameAvailableRequestSchema.safeParse(req.body);

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

  let { nameToCheck } = requestValidationResult.data;
  nameToCheck = cleanCommunityName(nameToCheck);

  try {
    const prismaClient = new PrismaClient();

    const isNameAvailable = await daoFnIsCommunityNameAvailable(nameToCheck, prismaClient);

    logger.info('Community name is %s', isNameAvailable ? 'available' : 'not available');

    const responseBody: IsCommunityNameAvailableResponse = {
      isNameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while checking for community name availability: %s', err.message);
      res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
      return;
    }

    logger.warn('Error while checking for community name availability: %o', err);
    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
};

export default handler;
