import { Button, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { useRouter } from 'next/router';
import { useMemo } from 'react';
import { useSubscriptionActions, useUserSubscriptions } from '@knowii/client';
import { Loader } from '../common/loader';
import { Section } from '../layout/section';
import { formatPrice } from '@knowii/common';
import { useTranslations } from 'next-intl';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface SubscriptionProps {}

// FIXME rework, move to communities and integrate on that screen
export function Subscription(_props: SubscriptionProps) {
  const router = useRouter();
  const { locale } = useRouter();
  const t = useTranslations();
  const { currentlySubscribedPlan, loading: loadingSubscriptions } = useUserSubscriptions();
  const { manageSubscription, loading } = useSubscriptionActions();
  const highlightColor = useColorModeValue('primary.500', 'primary.400');

  const price = useMemo(() => currentlySubscribedPlan?.prices[0], [currentlySubscribedPlan]);

  const formattedPrice = useMemo(
    () =>
      price
        ? formatPrice({
            locale,
            currency: price.currency,
            amount: price.unit_amount ?? 0,
          })
        : '',
    [price, locale],
  );

  // FIXME ugly workaround to be able to set the key dynamically
  // FIXME Make sure this works
  // eslint-disable-next-line
  let priceIntervalTranslationKey: any = '';
  if (price) {
    priceIntervalTranslationKey = `subscriptionTable.intervals.${price.interval}`;
  }

  return (
    <Section title={t('subscription.title')}>
      {loadingSubscriptions ? (
        <Loader />
      ) : currentlySubscribedPlan ? (
        <VStack align="start">
          <Text>{t('subscription.currentlySubscribed')}</Text>
          <Text fontWeight="bold" fontSize="2xl" color={highlightColor}>
            {currentlySubscribedPlan.name} – {formattedPrice}
            {price && (
              <Text as="small" fontSize="sm" opacity={0.75}>
                {' '}
                / {price.interval_count && price.interval_count > 1 ? price.interval_count : ''} {t(priceIntervalTranslationKey)}
              </Text>
            )}
          </Text>
          <Button variant="outline" colorScheme="primary" isLoading={loading} onClick={manageSubscription}>
            {t('subscription.manageSubscription')}
          </Button>
        </VStack>
      ) : (
        <VStack align="start">
          <Text>{t('subscription.notSubscribed')}</Text>
          <Button variant="outline" colorScheme="primary" isLoading={loading} onClick={() => router.push('/#pricing')}>
            {t('subscription.upgradeAccount')}
          </Button>
        </VStack>
      )}
    </Section>
  );
}
export default Subscription;
