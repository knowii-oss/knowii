// @ts-check

import { root } from './helpers.mjs';

const isRunningOnLocalhost = !!process.env['NEXT_PUBLIC_IS_RUNNING_ON_LOCALHOST'];

const languages = ['en', 'fr'];

/**
 * @type {import('next-i18next').UserConfig}
 */
export const i18nConfig = {
  // https://www.i18next.com/overview/configuration-options#logging
  debug: process.env.NODE_ENV === 'development',
  //saveMissing: true,
  //saveMissingTo: 'current',
  fallbackLng: false,
  supportedLngs: languages,
  i18n: {
    defaultLocale: 'en',
    locales: languages,
  },
  react: {
    useSuspense: false,
  },
  // To avoid issues when deploying to Vercel
  // Reference: https://github.com/i18next/next-i18next/blob/master/examples/simple/next-i18next.config.js
  localePath:
    typeof window === 'undefined' && isRunningOnLocalhost
      ? root('apps/knowii/public/locales')
      : typeof window === 'undefined'
      ? './locales'
      : '/locales',
  reloadOnPrerender: process.env.NODE_ENV === 'development',
};
