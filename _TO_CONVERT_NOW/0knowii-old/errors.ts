import { ErrorMessageOptions } from 'zod-error';

export const errorMessageOptions: ErrorMessageOptions = {
  prefix: '🔥 ',
  delimiter: {
    error: '. 🔥 ',
  },
  suffix: '.',
  transform: ({ errorMessage, index }) => `Error #${index + 1}: ${errorMessage}`,
};
