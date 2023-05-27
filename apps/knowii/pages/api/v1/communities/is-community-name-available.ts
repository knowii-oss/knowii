import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import {
  forbiddenCommunityNameCharactersRegex,
  errorClientNotAuthenticated,
  errorInternalServerError,
  errorInvalidCommunityName,
  errorNoCommunityNameProvided,
  hasErrorMessage,
  maxLengthCommunityName,
  errorCommunityNameTooLong,
  minLengthCommunityName,
  errorCommunityNameTooShort,
  IsCommunityNameAvailableResponse,
  cleanCommunityName,
} from '@knowii/common';
import { PrismaClient } from '@prisma/client';
import { daoFnIsCommunityNameAvailable } from '@knowii/server';

export default async function handler(req: NextApiRequest, res: NextApiResponse<IsCommunityNameAvailableResponse>) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  console.log('Handling communities:is-community-name-available request');

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

  // FIXME check shape with Zod and IsCommunityNameAvailableRequest
  let { nameToCheck = '' } = req.body;

  nameToCheck = cleanCommunityName(nameToCheck);

  if (!nameToCheck || '' === nameToCheck) {
    console.warn(errorNoCommunityNameProvided.description);
    return res.status(400).json({
      error: errorNoCommunityNameProvided.code,
      errorDescription: errorNoCommunityNameProvided.description,
    });
  } else if (forbiddenCommunityNameCharactersRegex.test(nameToCheck)) {
    console.warn(errorInvalidCommunityName.description);
    return res.status(400).json({
      error: errorInvalidCommunityName.code,
      errorDescription: errorInvalidCommunityName.description,
    });
  } else if (nameToCheck.trim().length > maxLengthCommunityName) {
    console.warn(errorCommunityNameTooLong.description);
    return res.status(400).json({
      error: errorCommunityNameTooLong.code,
      errorDescription: errorCommunityNameTooLong.description,
    });
  } else if (nameToCheck.trim().length < minLengthCommunityName) {
    console.warn(errorCommunityNameTooShort.description);
    return res.status(400).json({
      error: errorCommunityNameTooShort.code,
      errorDescription: errorCommunityNameTooShort.description,
    });
  }
  console.log('Request validated. Community name to check: ', nameToCheck);

  try {
    const prismaClient = new PrismaClient();

    const isNameAvailable = await daoFnIsCommunityNameAvailable(nameToCheck, prismaClient);

    const responseBody: IsCommunityNameAvailableResponse = {
      isNameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while checking for community name availability: ${err.message}`);
      res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
      return;
    }

    console.warn(`Error while checking for community name availability: ${err}`);
    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
