import { Button } from '@chakra-ui/react';
import { useUser } from '@supabase/auth-helpers-react';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';

export default function Hero() {
  const { t } = useTranslation('home');

  const user = useUser();
  return (
    <div className="flex flex-col text-center px-8 py-36 bg-primary-500 dark:bg-steel-500 gap-2">
      <h1 className="text-white">{t('hero.title')}</h1>
      <h2 className="text-white">{t('hero.description')}</h2>

      <div className="flex flex-col md:flex-row gap-3 mt-5 justify-center">
        <Button as={Link} href={user ? '/app' : '/auth/signup'} size="lg" colorScheme="primary">
          {user ? t('dashboardButton') : t('signupButton')} &rarr;
        </Button>
      </div>
    </div>
  );
}
