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

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

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

  console.log('Community name to check: ', nameToCheck);

  try {
    const prisma = new PrismaClient();

    const communitiesWithThatName = await prisma.communities.count({
      where: {
        name: nameToCheck,
      },
    });

    const isNameAvailable = communitiesWithThatName === 0;

    if (isNameAvailable) {
      console.log('The community name is available');
    } else {
      console.log('The community name is not available');
    }

    const responseBody: IsCommunityNameAvailableResponse = {
      isNameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while checking for community name availability: ${err.message}`);
      res.status(500).json({
        error: {
          statusCode: 500,
          errorDescription: errorInternalServerError.description,
        },
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
