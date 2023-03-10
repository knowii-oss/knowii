// @ts-check

import { root } from './helpers.mjs';

/**
 * @type {import('next-i18next').UserConfig}
 */
export const i18nConfig = {
  // https://www.i18next.com/overview/configuration-options#logging
  debug: process.env.NODE_ENV === 'development',
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'fr'],
  },
  // To avoid issues when deploying to Vercel
  // Reference: https://github.com/i18next/next-i18next/blob/master/examples/simple/next-i18next.config.js
  localePath: typeof window === 'undefined' ? root('apps/knowii/public/locales') : '/locales',
  reloadOnPrerender: process.env.NODE_ENV === 'development',
};
