import { Button, Stack, useColorModeValue } from '@chakra-ui/react';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';
import { useMobileBreakpoint } from './layout';
import { I18N_TRANSLATIONS_COMMON } from '@knowii/common';

export interface MenuProps {
  mobileMode: boolean;
}

export function Menu({ mobileMode }: MenuProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_COMMON);
  const user = useUser();
  const menuItemColor = useColorModeValue('blue.500', 'white');
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
          aria-label={''} // FIXME set aria label on all elements
          display="block"
          variant="ghost"
          fontWeight="normal"
          color={menuItemColor}
        >
          <div className="px-4 pt-2 pb-0">{item.label}</div>
        </Button>
      ))}
    </Stack>
  );
}

export default Menu;
