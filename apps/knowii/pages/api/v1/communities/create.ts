import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
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
} from '@knowii/common';
import { daoFnCreateCommunity, errorMessageOptions } from '@knowii/server';
import { PrismaClient } from '@prisma/client';

export default async function handler(req: NextApiRequest, res: NextApiResponse<CreateCommunityResponse>): Promise<void> {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  console.log('Handling communities:create request');

  const supabaseClient = createServerSupabaseClient({ req, res });

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
    console.warn(`${errorInputValidation.description}. Error(s) detected: `, errorMessage);

    res.status(400).json({
      error: errorInputValidation.code,
      errorDescription: errorInputValidation.description,
      errorDetails: errorMessage,
    });
    return;
  }

  console.log('Request validated. Data: ', requestValidationResult.data);

  const { name, description } = requestValidationResult.data;
  const slug = generateSlug(name);

  const creationPayload: CreateCommunityInput = {
    name,
    description,
    slug,
  };

  console.log('Proceeding with the creation: ', creationPayload);

  try {
    const prismaClient = new PrismaClient();

    const createdCommunity = await daoFnCreateCommunity(creationPayload, prismaClient);

    console.log('Created community: ', createdCommunity);

    // FIXME continue here

    const responseBody: CreateCommunityResponse = {
      data: createdCommunity,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while creating a new community: ${err.message}`);

      return res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
    }

    console.warn(`Error while creating a new community: ${err}`);

    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
