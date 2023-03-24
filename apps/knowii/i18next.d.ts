import 'i18next';

import type account from './public/locales/en/account.json';
import type app from './public/locales/en/app.json';
import type auth from './public/locales/en/auth.json';
import type blog from './public/locales/en/blog.json';
import type common from './public/locales/en/common.json';
import type home from './public/locales/en/en/home.json';
import type lost from './public/locales/en/en/lost.json';

interface I18nNamespaces {
  account: typeof account;
  app: typeof app;
  auth: typeof auth;
  blog: typeof blog;
  common: typeof common;
  home: typeof home;
  lost: typeof lost;
}

declare module 'i18next' {
  interface CustomTypeOptions {
    returnNull: false;
  }
}
