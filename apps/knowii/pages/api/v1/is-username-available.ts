import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import {
  allowedUsernameCharactersRegex,
  errorInternalServerError,
  errorInvalidUsername,
  errorNoUsernameProvided,
  hasErrorMessage,
} from '@knowii/common';

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  const supabaseClient = createServerSupabaseClient({ req, res });

  const { usernameToCheck = '' } = req.body;

  if (!usernameToCheck || '' === usernameToCheck.trim()) {
    console.warn('No username provided');
    return res.status(400).json({
      error: errorNoUsernameProvided,
      description: 'You must provide a username to check',
    });
  } else if (allowedUsernameCharactersRegex.test(usernameToCheck)) {
    console.warn('The provided username contains forbidden characters');
    return res.status(400).json({
      error: errorInvalidUsername,
      description: 'The provided username contains forbidden characters',
    });
  }

  console.log('Username to check: ', usernameToCheck);

  try {
    // WARNING: The argument name MUST match the exact argument name of the function declared in supabase-db-seed.sql
    const checkResult = await supabaseClient.rpc('is_username_available', { username_to_check: usernameToCheck });

    if (checkResult.error) {
      console.warn(
        `Error while checking for username availability: ${JSON.stringify(checkResult.error)}. DB access status code: ${
          checkResult.status
        }`,
      );
      return res.status(500).json({
        error: errorInternalServerError,
        description: 'We have encountered an unexpected issue',
      });
    }

    //console.log('Check result: ', checkResult);
    const isUsernameAvailable = checkResult.data;

    if (isUsernameAvailable) {
      console.log('The username is available');
    }

    const responseBody = {
      isUsernameAvailable,
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
