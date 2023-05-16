import { Box, Divider, Heading, SimpleGrid, Stack, Text, VStack } from '@chakra-ui/react';
import { PropsWithChildren, ReactElement } from 'react';
import { Provider } from '@supabase/supabase-js';
import SocialSigninButton from './social-signin-button';
import { useTranslations } from 'next-intl';

interface AuthFormWrapperProps {
  title: string;
  description?: string | ReactElement;
  showSocialAuth?: boolean;
  redirectAfterSignin?: string;
  enabledAuthProviders?: Provider[];
}

export function AuthFormWrapper({
  title,
  description,
  children,
  showSocialAuth,
  redirectAfterSignin,
  enabledAuthProviders,
}: PropsWithChildren<AuthFormWrapperProps>) {
  const t = useTranslations('authFormWrapper');

  return (
    <VStack spacing={6} align="stretch">
      <Stack spacing={2}>
        <Heading fontSize="4xl">{title}</Heading>
        {description && <Text color="gray.500">{description}</Text>}
      </Stack>
      <Box>{children}</Box>

      {showSocialAuth && enabledAuthProviders && (
        <>
          <Divider my={8} />
          <Heading as="h4" fontSize="lg" mb={3}>
            {t('continueWith')}
          </Heading>

          <SimpleGrid columns={2} gap={2}>
            {enabledAuthProviders.includes('google') ? (
              <SocialSigninButton provider="google" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('apple') ? (
              <SocialSigninButton provider="apple" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('twitter') ? (
              <SocialSigninButton provider="twitter" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('facebook') ? (
              <SocialSigninButton provider="facebook" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('linkedin') ? (
              <SocialSigninButton provider="linkedin" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('discord') ? (
              <SocialSigninButton provider="discord" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('slack') ? (
              <SocialSigninButton provider="slack" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('github') ? (
              <SocialSigninButton provider="github" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('azure') ? (
              <SocialSigninButton provider="azure" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('bitbucket') ? (
              <SocialSigninButton provider="bitbucket" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('gitlab') ? (
              <SocialSigninButton provider="gitlab" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('notion') ? (
              <SocialSigninButton provider="notion" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('spotify') ? (
              <SocialSigninButton provider="spotify" redirectAfterSignin={redirectAfterSignin} />
            ) : null}

            {enabledAuthProviders.includes('twitch') ? (
              <SocialSigninButton provider="twitch" redirectAfterSignin={redirectAfterSignin} />
            ) : null}
          </SimpleGrid>
        </>
      )}
    </VStack>
  );
}

export default AuthFormWrapper;
