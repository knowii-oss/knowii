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
} from '@knowii/common';

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

  const { nameToCheck = '' } = req.body;

  if (!nameToCheck || '' === nameToCheck.trim()) {
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

  console.log('Name to check: ', nameToCheck);

  try {
    // WARNING: The argument name MUST match the exact argument name of the function declared in supabase-db-seed.sql
    const checkResult = await supabaseClient.rpc('is_community_name_available', { name_to_check: nameToCheck });

    if (checkResult.error) {
      console.warn(
        `Error while checking for community name availability: ${JSON.stringify(checkResult.error)}. DB access status code: ${
          checkResult.status
        }`,
      );
      return res.status(500).json({
        error: errorInternalServerError.code,
        errorDescription: errorInternalServerError.description,
      });
    }

    //console.log('Check result: ', checkResult);
    const isNameAvailable = checkResult.data;

    if (isNameAvailable) {
      console.log('The name is available');
    }

    // FIXME use zod schema
    const responseBody = {
      isNameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      res.status(500).json({ error: { statusCode: 500, message: err.message } });
      return;
    }
    res.status(500).json({ error: { statusCode: 500, message: err } });
  }
}
