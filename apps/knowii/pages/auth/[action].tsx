import { Box, Container, Flex, HStack, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import Head from 'next/head';
import { useMemo } from 'react';
import { useTranslations } from 'next-intl';
import { ColorModeSwitch, ForgotPasswordForm, LanguageSwitch, Logo, ResetPasswordForm, SigninForm, SignupForm } from '@knowii/client-ui';
import { Database, isValidAuthAction, redirectPath, SIGN_IN_URL } from '@knowii/common';
import { i18nConfig } from '../../../../i18n.config.mjs';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  const action = ctx.params?.action as string;

  if (!action || !isValidAuthAction(action)) {
    return {
      redirect: {
        destination: SIGN_IN_URL,
        permanent: false,
      },
    };
  }

  const supabaseClient = createServerSupabaseClient<Database>(ctx);
  const { data: session } = await supabaseClient.auth.getSession();

  const isUserLoggedIn = !!session?.session;

  // if user is not logged in and tries to access reset-password page, redirect to signin page
  if (!isUserLoggedIn && action === 'reset-password') {
    return {
      redirect: {
        destination: SIGN_IN_URL,
        permanent: false,
      },
    };
  }

  // if user is logged in and tries to access any other auth page, redirect to home page
  if (isUserLoggedIn && action !== 'reset-password') {
    return {
      redirect: {
        destination: redirectPath,
        permanent: false,
      },
    };
  }

  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;
  const messages = (await import(`../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  return {
    props: {
      messages,
      action,
    },
  };
};

export default function AuthPage({ action }: { action: string }) {
  const t = useTranslations();

  const actionTitles: Record<string, string> = useMemo(
    () => ({
      signup: t('authPage.signUp.pageTitle'),
      signin: t('authPage.signIn.pageTitle'),
      forgotPassword: t('authPage.forgotPassword.pageTitle'),
      resetPassword: t('authPage.resetPassword.pageTitle'),
    }),
    [t],
  );
  const title = useMemo(() => actionTitles[action as string], [action, actionTitles]);

  return (
    <>
      <Head>
        <title>{title}</title>
        <link rel="icon" type="image/png" href="/favicon.png" />
      </Head>
      <Box bg={useColorModeValue('gray.50', 'gray.900')} w="full">
        <Flex
          align="center"
          justify="center"
          px={6}
          py={12}
          bg={useColorModeValue('white', 'gray.800')}
          maxW={{ base: 'full', lg: '50%' }}
          minH="100vh"
        >
          <Container maxW="lg">
            <VStack align="stretch" spacing={12}>
              <Logo />
              {action === 'signup' && <SignupForm />}
              {action === 'signin' && <SigninForm />}
              {action === 'forgot-password' && <ForgotPasswordForm />}
              {action === 'reset-password' && <ResetPasswordForm />}

              <HStack justify="space-between">
                <HStack>
                  <ColorModeSwitch />
                  <LanguageSwitch />
                </HStack>

                <Text py={1} color="gray.500">
                  © Knowii. {t('footer.allRightsReserved', { ns: 'common' })}
                </Text>
              </HStack>
            </VStack>
          </Container>
        </Flex>
      </Box>
    </>
  );
}
