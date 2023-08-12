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

    res.status(400).json({
      error: errorInputValidation.code,
      errorDescription: errorInputValidation.description,
      errorDetails: errorMessage,
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
      return res.status(400).json({
        error: errorCommunityNotFound.code,
        errorDescription: errorCommunityNotFound.description,
      });
    }

    logger.info('Found community: %o', foundCommunity);

    const responseBody: GetCommunityResponse = {
      data: foundCommunity,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while loading a community: %o', err.message);

      return res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
    }

    logger.warn('Error while loading a community: %o', err);

    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
