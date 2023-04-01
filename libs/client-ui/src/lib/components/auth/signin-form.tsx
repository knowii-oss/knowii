import {
  Alert,
  AlertIcon,
  AlertTitle,
  Button,
  Divider,
  FormControl,
  FormHelperText,
  FormLabel,
  Heading,
  Input,
  InputGroup,
  InputLeftElement,
  Link,
  SimpleGrid,
  Stack,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import NextLink from 'next/link';
import { useRouter } from 'next/router';
import { FormEvent, useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FaAt, FaLock } from 'react-icons/fa';
import { useTranslations } from 'next-intl';
import { useAuthRedirectUrl } from '@knowii/client';
import { Database, redirectPath } from '@knowii/common';
import { Loader } from '../common/loader';
import { AuthFormWrapper } from './auth-form-wrapper';
import { SigninModeSwitch, SigninMode } from './signin-mode-switch';
import { SocialSigninButton } from './social-signin-button';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface SigninFormProps {}

export function SigninForm(_props: SigninFormProps) {
  const t = useTranslations('signinForm');

  const [mode, setMode] = useState<SigninMode>(SigninMode.MagicLink);
  const router = useRouter();
  const { query } = router;

  const supabaseClient = useSupabaseClient<Database>();

  const redirectAfterSignin = query.redirectAfterSignin ? decodeURIComponent(query.redirectAfterSignin as string) : redirectPath;

  const redirectTo = useAuthRedirectUrl(
    mode === SigninMode.MagicLink ? `/auth/callback?redirectAfterSignin=${encodeURIComponent(redirectAfterSignin)}` : redirectAfterSignin,
  );

  const { register, handleSubmit, setError, clearErrors, formState, reset } = useForm<{
    email: string;
    password?: string;
    serverError?: void;
  }>();
  const { isSubmitting, isSubmitted, isSubmitSuccessful } = formState;

  useEffect(() => {
    const {
      data: { subscription },
    } = supabaseClient.auth.onAuthStateChange((event) => {
      if (event === 'SIGNED_IN') router.replace(redirectAfterSignin);
    });

    return () => subscription.unsubscribe();
  }, [supabaseClient, redirectAfterSignin]); // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = (e: FormEvent) => {
    clearErrors('serverError');
    handleSubmit(async ({ email, password }) => {
      const { error } =
        mode === SigninMode.Password && password
          ? await supabaseClient.auth.signInWithPassword({ email, password })
          : await supabaseClient.auth.signInWithOtp({
              email,
              options: {
                emailRedirectTo: redirectTo,
              },
            });

      if (error) {
        setError('serverError', {
          type: 'invalidCredentials',
        });
        return;
      }
    })(e);
  };

  useEffect(() => reset(), [mode]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <AuthFormWrapper
      title={t('title')}
      description={
        <>
          {`${t('description')} ${t('noAccount')} `}
          <Link as={NextLink} href="/auth/signup" display="inline-block" color="primary.500">
            {t('signupButton')} &rarr;
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit}>
        <Stack spacing={5} align="start">
          <SigninModeSwitch activeMode={mode} onChange={setMode} />

          {isSubmitted &&
            (isSubmitSuccessful ? (
              mode === SigninMode.Password ? (
                <div className="w-full">
                  <Loader />
                </div>
              ) : (
                <Alert status="success" rounded="lg">
                  <AlertIcon />
                  <AlertTitle>{t('linkSent')}</AlertTitle>
                </Alert>
              )
            ) : (
              <Alert status="error" rounded="lg">
                <AlertIcon />
                <AlertTitle>{t('errorMessage')}</AlertTitle>
              </Alert>
            ))}

          {(!isSubmitted || !isSubmitSuccessful) && (
            <>
              {/* Email field */}
              <FormControl>
                <FormLabel>{t('fields.email')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaAt />
                  </InputLeftElement>

                  <Input
                    type="email"
                    autoComplete="email"
                    {...register('email', {
                      required: true,
                    })}
                  />
                </InputGroup>
              </FormControl>

              {/* Password field */}
              {mode === 'password' && (
                <FormControl>
                  <FormLabel>{t('fields.password')}</FormLabel>
                  <InputGroup>
                    <InputLeftElement color="gray.300">
                      <FaLock />
                    </InputLeftElement>
                    <Input type="password" autoComplete="password" {...register('password', { required: true })} />
                  </InputGroup>
                  <FormHelperText>
                    <Link as={NextLink} href="/auth/forgot-password" color="primary.500">
                      {t('forgotPassword')}
                    </Link>
                  </FormHelperText>
                </FormControl>
              )}

              {/* Submit button */}
              <Button w="full" colorScheme="primary" type="submit" isLoading={isSubmitting}>
                {mode === 'password' ? t('submitButton') : t('sendLinkButton')}
              </Button>
            </>
          )}
        </Stack>
      </form>

      <Divider my={8} />
      <Heading as="h4" fontSize="lg" mb={3}>
        {t('continueWith')}
      </Heading>

      <SimpleGrid columns={2} gap={2}>
        <SocialSigninButton provider="google" redirectAfterSignin={redirectAfterSignin} />
        <SocialSigninButton provider="github" redirectAfterSignin={redirectAfterSignin} />
      </SimpleGrid>
    </AuthFormWrapper>
  );
}

export default SigninForm;
