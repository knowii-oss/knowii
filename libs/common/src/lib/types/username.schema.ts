import { z } from 'zod';
import { ALLOWED_USER_USERNAME_CHARACTERS_REGEX, MAX_LENGTH_USER_USERNAME, MIN_LENGTH_USER_USERNAME } from '../constants';

export const usernameSchema = z
  .string()
  .min(MIN_LENGTH_USER_USERNAME, {
    message: `Please choose a username (between ${MIN_LENGTH_USER_USERNAME} and ${MAX_LENGTH_USER_USERNAME} characters)`,
  })
  .max(MAX_LENGTH_USER_USERNAME, {
    message: `The username is too long (maximum ${MAX_LENGTH_USER_USERNAME} characters)`,
  })
  .regex(new RegExp(ALLOWED_USER_USERNAME_CHARACTERS_REGEX), {
    message: 'Please use only letters, numbers, or hyphens',
  });

export type Username = z.infer<typeof usernameSchema>;
