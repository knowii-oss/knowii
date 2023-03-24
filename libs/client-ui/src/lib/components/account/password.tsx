import { Button } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import Link from 'next/link';
import { AccountSection } from './account-section';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface PasswordProps {}

export function Password(_props: PasswordProps) {
  const { t } = useTranslation('account');

  return (
    <AccountSection title={t('password')}>
      <Button as={Link} href="/auth/reset-password" variant="outline" colorScheme="primary">
        {t('changeMyPassword')} &rarr;
      </Button>
    </AccountSection>
  );
}

export default Password;
