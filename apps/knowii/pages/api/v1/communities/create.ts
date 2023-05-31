import { createPagesServerClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import { generateErrorMessage } from 'zod-error';
import {
  errorClientNotAuthenticated,
  errorInternalServerError,
  hasErrorMessage,
  createCommunityRequestSchema,
  CreateCommunityResponse,
  errorInputValidation,
  generateSlug,
  CreateCommunityInput,
  getLogger,
} from '@knowii/common';
import { daoFnCreateCommunity, errorMessageOptions } from '@knowii/server';
import { PrismaClient } from '@prisma/client';

export default async function handler(req: NextApiRequest, res: NextApiResponse<CreateCommunityResponse>) {
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
      errorDetails: errorClientNotAuthenticated.description,
    });
  }

  const requestValidationResult = createCommunityRequestSchema.safeParse(req.body);

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

  const { name, description } = requestValidationResult.data;
  const slug = generateSlug(name);

  const creationPayload: CreateCommunityInput = {
    name,
    description,
    slug,
  };

  logger.info('Proceeding with the creation: %o', creationPayload);

  try {
    const prismaClient = new PrismaClient();

    const createdCommunity = await daoFnCreateCommunity(creationPayload, prismaClient);

    logger.info('Created community: %o', createdCommunity);

    const responseBody: CreateCommunityResponse = {
      data: createdCommunity,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      logger.warn('Error while creating a new community: %', err.message);

      return res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
    }

    logger.warn('Error while creating a new community: %o', err);

    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
