import { Button, Stack, useColorModeValue } from '@chakra-ui/react';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';
import { useMobileBreakpoint } from './layout';
import { I18N_TRANSLATIONS_COMMON } from '@knowii/common';

export interface MenuProps {
  mobileMode?: boolean;
}

export function Menu({ mobileMode }: MenuProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_COMMON);
  const user = useUser();
  const menuItemColor = useColorModeValue('gray.600', 'gray.200');
  const isMobile = useMobileBreakpoint();
  const isHidden = isMobile !== mobileMode;

  const menuItems = [
    {
      label: t('menu.features'),
      link: '/#features',
    },
    {
      label: t('menu.pricing'),
      link: '/#pricing',
    },
    {
      label: t('menu.blog'),
      link: '/blog',
    },
  ]
    // add dashboard link only if user is logged in
    .concat(user ? [{ label: t('menu.dashboard'), link: '/app' }] : []);

  return (
    <Stack hidden={isHidden} direction={mobileMode ? 'column' : 'row'} spacing={4} align={mobileMode ? 'start' : 'center'}>
      {menuItems.map((item, i) => (
        <Button
          as={Link}
          key={i}
          href={item.link}
          fontSize={mobileMode ? 'lg' : 'base'}
          display="block"
          variant="link"
          fontWeight="normal"
          color={menuItemColor}
        >
          {item.label}
        </Button>
      ))}
    </Stack>
  );
}

export default Menu;