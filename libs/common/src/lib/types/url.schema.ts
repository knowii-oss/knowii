import { z } from 'zod';

export const urlSchema = z.object({
  // Only web pages: http and https (the server refuses the other schemes too)
  url: z.url({
    protocol: /^https?$/,
    error: 'Enter the full address of a web page, starting with https://',
  }),
});
