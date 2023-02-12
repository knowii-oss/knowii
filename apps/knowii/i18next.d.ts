import 'i18next';
import type account from './public/locales/en/account.json';
import type app from './public/locales/en/app.json';
import type auth from './public/locales/en/auth.json';
import type blog from './public/locales/en/blog.json';
import type common from './public/locales/en/common.json';
import type home from './public/locales/en/en/home.json';

interface I18nNamespaces {
  common: typeof common;
  home: typeof home;
  blog: typeof blog;
  auth: typeof auth;
  app: typeof app;
  account: typeof account;
}

declare module 'i18next' {
  interface CustomTypeOptions {
    returnNull: false;
  }
}
