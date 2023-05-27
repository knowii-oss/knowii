import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import {
  errorClientNotAuthenticated,
  errorInternalServerError,
  hasErrorMessage,
  errorNoCommunitySlugProvided,
  errorInvalidCommunitySlug,
  forbiddenCommunitySlugCharactersRegex,
  maxLengthCommunitySlug,
  minLengthCommunitySlug,
  errorCommunitySlugTooLong,
  errorCommunitySlugTooShort,
  IsCommunitySlugAvailableResponse,
} from '@knowii/common';
import { PrismaClient } from '@prisma/client';
import { daoFnIsCommunitySlugAvailable } from '@knowii/server';

export default async function handler(req: NextApiRequest, res: NextApiResponse<IsCommunitySlugAvailableResponse>) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  console.log('Handling communities:is-community-slug-available request');

  const supabaseClient = createServerSupabaseClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    return res.status(401).json({
      error: errorClientNotAuthenticated.code,
      errorDescription: errorClientNotAuthenticated.description,
    });
  }

  // FIXME check shape with Zod and IsCommunitySlugAvailableRequest
  let { slugToCheck = '' } = req.body;

  if (!slugToCheck || '' === slugToCheck) {
    console.warn(errorNoCommunitySlugProvided.description);
    return res.status(400).json({
      error: errorNoCommunitySlugProvided.code,
      errorDescription: errorNoCommunitySlugProvided.description,
    });
  } else if (forbiddenCommunitySlugCharactersRegex.test(slugToCheck)) {
    console.warn(errorInvalidCommunitySlug.description);
    return res.status(400).json({
      error: errorInvalidCommunitySlug.code,
      errorDescription: errorInvalidCommunitySlug.description,
    });
  } else if (slugToCheck.trim().length > maxLengthCommunitySlug) {
    console.warn(errorCommunitySlugTooLong.description);
    return res.status(400).json({
      error: errorCommunitySlugTooLong.code,
      errorDescription: errorCommunitySlugTooLong.description,
    });
  } else if (slugToCheck.trim().length < minLengthCommunitySlug) {
    console.warn(errorCommunitySlugTooShort.description);
    return res.status(400).json({
      error: errorCommunitySlugTooShort.code,
      errorDescription: errorCommunitySlugTooShort.description,
    });
  }

  console.log('Request validated. Community slug to check: ', slugToCheck);

  try {
    const prismaClient = new PrismaClient();

    const isSlugAvailable = await daoFnIsCommunitySlugAvailable(slugToCheck, prismaClient);

    const responseBody: IsCommunitySlugAvailableResponse = {
      isSlugAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while checking for community slug availability: ${err.message}`);
      res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
      return;
    }

    console.warn(`Error while checking for community slug availability: ${err}`);
    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
