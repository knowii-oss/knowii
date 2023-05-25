import { NextApiRequest, NextApiResponse } from 'next';
import {
  cleanUsername,
  errorInternalServerError,
  errorInvalidUsername,
  errorNoUsernameProvided,
  errorUsernameTooLong,
  errorUsernameTooShort,
  forbiddenUsernameCharactersRegex,
  hasErrorMessage,
  IsUsernameAvailableResponse,
  maxLengthUsername,
  minLengthUsername,
} from '@knowii/common';
import { PrismaClient } from '@prisma/client';

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  let { usernameToCheck = '' } = req.body;

  usernameToCheck = cleanUsername(usernameToCheck);

  if (!usernameToCheck || '' === usernameToCheck) {
    console.warn(errorNoUsernameProvided.description);
    return res.status(400).json({
      error: errorNoUsernameProvided.code,
      errorDescription: errorNoUsernameProvided.description,
    });
  } else if (forbiddenUsernameCharactersRegex.test(usernameToCheck)) {
    console.warn(errorInvalidUsername.description);
    return res.status(400).json({
      error: errorInvalidUsername.code,
      errorDescription: errorInvalidUsername.description,
    });
  } else if (usernameToCheck.trim().length > maxLengthUsername) {
    console.warn(errorUsernameTooLong.description);
    return res.status(400).json({
      error: errorUsernameTooLong.code,
      errorDescription: errorUsernameTooLong.description,
    });
  } else if (usernameToCheck.trim().length < minLengthUsername) {
    console.warn(errorUsernameTooShort.description);
    return res.status(400).json({
      error: errorUsernameTooShort.code,
      errorDescription: errorUsernameTooShort.description,
    });
  }

  console.log('Username to check: ', usernameToCheck);

  try {
    // WARNING: The argument name MUST match the exact argument name of the function declared in supabase-db-seed.sql
    const prisma = new PrismaClient();

    const usersWithThatUsername = await prisma.users.count({
      where: {
        username: usernameToCheck,
      },
    });

    const isUsernameAvailable = usersWithThatUsername === 0;

    if (isUsernameAvailable) {
      console.log('The username is available');
    } else {
      console.log('The username is not available');
    }

    const responseBody: IsUsernameAvailableResponse = {
      isUsernameAvailable,
    };

    return res.status(200).json(responseBody);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      console.warn(`Error while checking for username availability: ${err.message}`);
      res.status(500).json({
        error: {
          statusCode: 500,
          errorDescription: errorInternalServerError.description,
        },
      });
      return;
    }

    console.warn(`Error while checking for username availability: ${err}`);
    res.status(500).json({
      error: errorInternalServerError.code,
      errorDescription: errorInternalServerError.description,
    });
  }
}
