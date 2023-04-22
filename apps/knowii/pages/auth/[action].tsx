import { Box, Container, Flex, HStack, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import Head from 'next/head';
import { useMemo } from 'react';
import { useTranslations } from 'next-intl';
import { ColorModeSwitch, ForgotPasswordForm, LanguageSwitch, Logo, ResetPasswordForm, SigninForm, SignupForm } from '@knowii/client-ui';
import { AuthAction, Database, isValidAuthAction, redirectPath, SIGN_IN_URL } from '@knowii/common';
import { i18nConfig } from '../../../../i18n.config.mjs';
import { CustomPageProps } from '../_app';
import { Provider } from '@supabase/supabase-js';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface AuthPageProps {
  action: string;
  enabledAuthProviders: Provider[];
}

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps> & AuthPageProps> = async (ctx) => {
  const action = ctx.params?.action as string;

  if (!action || !isValidAuthAction(action) || action === 'callback') {
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

  const enabledAuthProviders: Provider[] = `${process.env.CONFIGURED_AUTH_PROVIDERS ? process.env.CONFIGURED_AUTH_PROVIDERS : ''}`.split(
    ',',
  ) as Provider[];

  return {
    props: {
      messages,
      action,
      enabledAuthProviders,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };
};

export default function AuthPage(props: AuthPageProps) {
  const t = useTranslations();

  const actionTitles: Record<AuthAction, string> = useMemo(
    () => ({
      signup: t('authPage.signUp.pageTitle'),
      signin: t('authPage.signIn.pageTitle'),
      'forgot-password': t('authPage.forgotPassword.pageTitle'),
      'reset-password': t('authPage.resetPassword.pageTitle'),
      callback: '',
    }),
    [t],
  );
  const title = useMemo(() => actionTitles[props.action as AuthAction], [props.action, actionTitles]);

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
              {props.action === 'signup' && <SignupForm />}
              {props.action === 'signin' && <SigninForm enabledAuthProviders={props.enabledAuthProviders} />}
              {props.action === 'forgot-password' && <ForgotPasswordForm />}
              {props.action === 'reset-password' && <ResetPasswordForm />}

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
