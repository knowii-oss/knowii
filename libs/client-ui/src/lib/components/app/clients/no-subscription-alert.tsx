import { Alert, Button, Text, VStack } from '@chakra-ui/react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface NoSubscriptionAlertProps {}

/**
 * Show an alert when the user has no active subscription
 * @constructor
 */
export function NoSubscriptionAlert(_props: NoSubscriptionAlertProps) {
  const t = useTranslations('noSubscriptionAlert');
  return (
    <Alert p={6} variant="left-accent" colorScheme="primary" rounded="md" mb={8}>
      <VStack align="start" gap={3}>
        <Text>{t('message')}</Text>

        <Button as={Link} href="/#pricing" colorScheme="primary">
          {t('upgradeButton')}
        </Button>
      </VStack>
    </Alert>
  );
}

export default NoSubscriptionAlert;
