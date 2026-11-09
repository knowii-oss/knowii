import { z } from 'zod';

export const knowiiApiResponseTypeSchema = z.enum([
  'validationIssue',
  'authenticationIssue',
  'authorizationIssue',
  'businessIssue',
  'internalError',
  'success',
  'notFound',
  'unsupportedMediaType',
  'methodNotAllowed',
  'tooManyRequests',
  'httpIssue',
]);

export type KnowiiApiResponseType = z.infer<typeof knowiiApiResponseTypeSchema>;
