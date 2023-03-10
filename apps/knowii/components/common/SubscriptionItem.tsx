import { Box, Button, Heading, ListItem, Text, UnorderedList, useColorModeValue, VStack } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { useMemo } from 'react';
import { defaultCurrency, localeCurrencies } from '../../config/i18n';
import { SubscriptionPlan } from '../../lib/payments';
import { formatPrice } from '../../lib/utils/helpers';
import { useSubscriptionActions } from '../../lib/client/subscriptions';

function SubscriptionItem({
  plan,
  billingInterval,
  isHighlighted,
  hideSubscribeButton,
}: {
  plan: SubscriptionPlan;
  billingInterval: 'month' | 'year';
  isHighlighted: boolean;
  hideSubscribeButton: boolean;
}) {
  const router = useRouter();
  const { t } = useTranslation('common');
  const { subscribe, loading } = useSubscriptionActions();
  const bgColor = useColorModeValue('primary.50', 'gray.900');
  const highlightColor = useColorModeValue('primary.500', 'primary.400');
  const localeCurrency = useMemo(
    () => ((router.locale && localeCurrencies[router.locale as keyof typeof localeCurrencies]) || defaultCurrency).toLowerCase(),
    [router.locale],
  );

  const price = useMemo(() => {
    const pricesWithBillingInterval = plan.prices.filter((price) => price.interval === billingInterval);

    const priceWithLocaleCurrency = pricesWithBillingInterval.find((price) => price.currency.toLowerCase() === localeCurrency);

    return priceWithLocaleCurrency || pricesWithBillingInterval[0];
  }, [plan, billingInterval, localeCurrency]);

  const formattedPrice = useMemo(
    () =>
      formatPrice({
        locale: router.locale,
        currency: price?.currency,
        amount: price?.unit_amount ?? 0,
      }),
    [price, router.locale],
  );

  return (
    <Box p={8} rounded="lg" bg={bgColor} border="4px solid" borderColor={isHighlighted ? highlightColor : 'transparent'}>
      <VStack spacing={4} align="stretch" justify="space-between" h="full">
        <VStack spacing={3} align="stretch">
          <Heading as="h3">{plan.name}</Heading>
          <Text fontSize="lg">{plan.description}</Text>
          {plan.features && (
            <UnorderedList opacity={0.75} pl={5} spacing={1}>
              {plan.features?.map((feature, i) => (
                <ListItem key={`plan-${plan.id}-feature-${i}`}>{feature}</ListItem>
              ))}
            </UnorderedList>
          )}
        </VStack>

        {price && (
          <VStack spacing={4} align="stretch">
            <Heading as="h4" fontSize="3xl" color={highlightColor}>
              {formattedPrice}{' '}
              <Text as="small" color="inherit" opacity={0.75} fontSize="lg" fontWeight="normal">
                / {price.interval_count && price.interval_count > 1 ? price.interval_count : ''} {t(`pricing.intervals.${price.interval}`)}
              </Text>
            </Heading>
            {!hideSubscribeButton && (
              <Button colorScheme="primary" w="full" size="lg" onClick={() => subscribe(price.id)} isLoading={loading}>
                {t('pricing.subscribeButton')}
              </Button>
            )}
          </VStack>
        )}
      </VStack>
    </Box>
  );
}

export default SubscriptionItem;
