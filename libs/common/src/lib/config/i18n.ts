//import { i18nConfig } from '../../../../../i18n.config.mjs';

export const localeNames = {
  fr: 'Français',
  en: 'English',
};

export type LocaleCode = keyof typeof localeNames;

// FIXME should be as below but breaks with Jest (because of .mjs import)
//export const defaultLocale = (i18nConfig.i18n.defaultLocale || 'en') as LocaleCode;
export const defaultLocale = 'en' as LocaleCode;

export const localeCurrencies = {
  fr: 'EUR',
  en: 'USD',
};

export const defaultCurrency = localeCurrencies[defaultLocale];
