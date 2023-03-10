import { IconButton, Menu, MenuButton, MenuItemOption, MenuList, MenuOptionGroup } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { useCallback } from 'react';
import { FaGlobeAmericas } from 'react-icons/fa';
import { LocaleCode, localeNames } from '../../config/i18n';

export default function LanguageSwitch() {
  const { t } = useTranslation('common');
  const router = useRouter();
  const { locales, locale: activeLocale, pathname, query, asPath } = router;

  const changeLocale = useCallback(
    (locale: string) => {
      document.cookie = `NEXT_LOCALE=${locale}`;
      router.replace({ pathname, query }, asPath, { locale });
    },
    [pathname, query, asPath, router],
  );

  return (
    <Menu placement="bottom-end">
      <MenuButton as={IconButton} variant="ghost" size="sm" icon={<FaGlobeAmericas />} aria-label={t('language')}></MenuButton>
      <MenuList>
        <MenuOptionGroup value={activeLocale} onChange={(locale) => changeLocale(locale as string)} title={t('language')} type="radio">
          {locales?.map((locale: string) => (
            <MenuItemOption key={`locale-${locale}`} as="a" value={locale}>
              {localeNames[locale as LocaleCode]}
            </MenuItemOption>
          ))}
        </MenuOptionGroup>
      </MenuList>
    </Menu>
  );
}
