import { i18nConfig } from '../../../../../next-i18next.config.mjs';

export const localeNames = {
  fr: 'Français',
  en: 'English',
};

export type LocaleCode = keyof typeof localeNames;

export const defaultLocale = (i18nConfig.i18n.defaultLocale || 'en') as LocaleCode;

export const localeCurrencies = {
  fr: 'EUR',
  en: 'USD',
};

export const defaultCurrency = localeCurrencies[defaultLocale];
