import { Box, Container, Flex, HStack, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import Head from 'next/head';
import { useMemo } from 'react';
import { ColorModeSwitch, ForgotPasswordForm, LanguageSwitch, Logo, ResetPasswordForm, SigninForm, SignupForm } from '@knowii/client-ui';
import { Database, I18N_TRANSLATIONS_AUTH, I18N_TRANSLATIONS_COMMON, redirectPath } from '@knowii/common';
import { i18nConfig } from '../../../../next-i18next.config.mjs';

const authActions = ['signup', 'signin', 'forgot-password', 'reset-password'];

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  const action = ctx.params?.action as string;

  if (!action || !authActions.includes(action))
    return {
      redirect: {
        destination: '/auth/signin',
        permanent: false,
      },
    };

  const supabaseClient = createServerSupabaseClient<Database>(ctx);
  const { data: session } = await supabaseClient.auth.getSession();

  const isUserLoggedIn = !!session?.session;

  // if user is not logged in and tries to access reset-password page, redirect to signin page
  if (!isUserLoggedIn && action === 'reset-password') {
    return {
      redirect: {
        destination: '/auth/signin',
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
  const translations = await serverSideTranslations(locale, [I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_AUTH], i18nConfig);

  return {
    props: {
      // eslint-disable-next-line
      ...translations,
      action,
    },
  };
};

export default function AuthPage({ action }: { action: string }) {
  const { t } = useTranslation([I18N_TRANSLATIONS_COMMON, I18N_TRANSLATIONS_AUTH]);

  const actionTitles: Record<string, string> = useMemo(
    () => ({
      signup: t('signup.pageTitle'),
      signin: t('signin.pageTitle'),
      forgotPassword: t('forgotPassword.pageTitle'),
      resetPassword: t('resetPassword.pageTitle'),
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
