import { Box, Container, Flex, HStack, Text, useColorModeValue, VStack } from '@chakra-ui/react';
import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { GetServerSideProps } from 'next';
import Head from 'next/head';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import {
  ColorModeSwitch,
  ForgotPasswordForm,
  LanguageSwitch,
  Logo,
  ResetPasswordForm,
  SigninForm,
  SignupForm,
  useDebounce,
} from '@knowii/client-ui';
import {
  forbiddenUsernameCharactersRegex,
  AuthAction,
  Database,
  isValidAuthAction,
  minLengthUsername,
  redirectPath,
  SIGN_IN_URL,
} from '@knowii/common';
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

  const [isUsernameAvailable, setIsUsernameAvailable] = useState(false);
  const [checkingUsernameAvailability, setCheckingUsernameAvailability] = useState(false);
  const [username, setUsername] = useState('');
  const usernameToCheck = useDebounce(username, 500);

  const lastAbortController = useRef<AbortController | null>();

  const verifyUsernameAvailability = async (abortController: AbortController) => {
    setCheckingUsernameAvailability(true);

    setIsUsernameAvailable(await checkIfUsernameIsAvailable(username, abortController.signal));

    if (lastAbortController.current) {
      lastAbortController.current = null;
    }

    setCheckingUsernameAvailability(false);
  };

  useEffect(() => {
    // When a new request is going to be issued,
    // the first thing to do is cancel the previous one
    if (lastAbortController.current) {
      lastAbortController.current.abort();
    }

    // Create new AbortController for the new request and store it in the ref
    const currentAbortController = new AbortController();
    lastAbortController.current = currentAbortController;

    verifyUsernameAvailability(currentAbortController);
  }, [usernameToCheck]);

  const checkIfUsernameIsAvailable = async (username: string, signal?: AbortSignal): Promise<boolean> => {
    if (username.trim().length < minLengthUsername) {
      return false;
    }

    if (forbiddenUsernameCharactersRegex.test(username)) {
      return false;
    }

    console.log('Checking username availability');

    try {
      const response = await fetch('/api/v1/is-username-available', {
        signal,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          // WARNING: The name must match the API parameter name
          usernameToCheck: username,
        }),
      });

      //console.log('Response: ', response);

      if (response.ok) {
        const responseBody = await response.json();

        if (responseBody.isUsernameAvailable) {
          console.log('Username is available');
          return true;
        } else {
          return false;
        }
      }
    } catch (error) {
      // FIXME handle error cases
      console.error(error);
    }

    return false;
  };

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
              {props.action === 'signup' && (
                <SignupForm
                  checkingUsernameAvailability={checkingUsernameAvailability}
                  isUsernameAvailable={isUsernameAvailable}
                  checkUsernameAvailability={setUsername}
                />
              )}
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
