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

  console.log('Community slug to check: ', slugToCheck);

  try {
    const prisma = new PrismaClient();

    const communitiesWithThatSlug = await prisma.communities.count({
      where: {
        slug: slugToCheck,
      },
    });

    const isSlugAvailable = communitiesWithThatSlug === 0;

    if (isSlugAvailable) {
      console.log('The community slug is available');
    } else {
      console.log('The community slug is not available');
    }

    const responseBody: IsCommunitySlugAvailableResponse = {
      isSlugAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while checking for community slug availability: ${err.message}`);
      res.status(500).json({
        error: {
          statusCode: 500,
          errorDescription: errorInternalServerError.description,
        },
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
