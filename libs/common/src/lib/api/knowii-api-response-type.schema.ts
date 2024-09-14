import { z } from 'zod';

export const knowiiApiResponseTypeSchema = z.enum([
  'validationIssue',
  'authenticationIssue',
  'authorizationIssue',
  'businessIssue',
  'internalError',
  'success',
  'notFound',
]);

export type KnowiiApiResponseType = z.infer<typeof knowiiApiResponseTypeSchema>;
