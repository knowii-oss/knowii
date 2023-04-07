import {
  Alert,
  AlertIcon,
  AlertTitle,
  Button,
  FormControl,
  FormLabel,
  Input,
  InputGroup,
  InputLeftElement,
  InputRightElement,
  Link,
  Stack,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import NextLink from 'next/link';
import { useRouter } from 'next/router';
import { FormEvent, useCallback, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FaAt, FaEye, FaEyeSlash, FaLock, FaUserAlt } from 'react-icons/fa';
import { redirectPath, SIGN_IN_URL } from '@knowii/common';
import { useAuthRedirectUrl } from '@knowii/client';
import { AuthFormWrapper } from './auth-form-wrapper';
import { useTranslations } from 'next-intl';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface SignupFormProps {}

export function SignupForm(_props: SignupFormProps) {
  const t = useTranslations('signupForm');
  const supabaseClient = useSupabaseClient();
  const router = useRouter();
  const redirectTo = useAuthRedirectUrl();
  const { register, handleSubmit, formState, setError, clearErrors } = useForm<{
    email: string;
    password: string;
    name: string;
    serverError?: void;
  }>();
  const { isSubmitting, isSubmitted, isSubmitSuccessful } = formState;
  const [isPasswordVisible, setPasswordVisible] = useState(false);

  const onSubmit = (e: FormEvent) => {
    clearErrors('serverError');
    handleSubmit(async ({ email, password, name }) => {
      const {
        data: { session, user: newUser },
        error,
      } = await supabaseClient.auth.signUp({
        email,
        password,
        options: {
          data: { full_name: name },
          emailRedirectTo: redirectTo,
        },
      });

      if (error || !newUser) {
        setError('serverError', { message: error?.message });
        return;
      }

      // if email confirmations are enabled, the user will have to confirm their email before they can sign in
      if (!session) {
        return;
      }

      // if email confirmations are disabled, the user will be signed in automatically and redirected
      await router.push(redirectPath);
    })(e);
  };

  const togglePassword = useCallback(() => setPasswordVisible(!isPasswordVisible), [isPasswordVisible]);

  return (
    <AuthFormWrapper
      title={t('title')}
      description={
        <>
          {`${t('description')} ${t('accountAlreadyExists')} `}
          <Link as={NextLink} href={SIGN_IN_URL} color="primary.500" display="inline-block">
            {t('signIn')} &rarr;
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit}>
        <Stack spacing={5}>
          {isSubmitted &&
            (isSubmitSuccessful ? (
              <Alert status="success" rounded="lg">
                <AlertIcon />
                <AlertTitle>{t('successMessage')}</AlertTitle>
              </Alert>
            ) : (
              <Alert status="error" rounded="lg">
                <AlertIcon />
                <AlertTitle>{t('errorMessage')}</AlertTitle>
              </Alert>
            ))}

          {(!isSubmitted || !isSubmitSuccessful) && (
            <>
              {/* Name field */}
              <FormControl>
                <FormLabel>{t('fields.name')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaUserAlt />
                  </InputLeftElement>
                  <Input autoComplete="name" {...register('name', { required: true })} />
                </InputGroup>
              </FormControl>

              {/* Email field */}
              <FormControl>
                <FormLabel>{t('fields.email')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaAt />
                  </InputLeftElement>

                  <Input required type="email" autoComplete="email" {...register('email', { required: true })} />
                </InputGroup>
              </FormControl>

              {/* Password field */}
              <FormControl>
                <FormLabel>{t('fields.password')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaLock />
                  </InputLeftElement>
                  <Input
                    required
                    type={isPasswordVisible ? 'text' : 'password'}
                    autoComplete="new-password"
                    {...register('password', { required: true })}
                  />
                  <InputRightElement color="primary.500" as="button" type="button" onClick={togglePassword}>
                    {isPasswordVisible ? <FaEyeSlash /> : <FaEye />}
                  </InputRightElement>
                </InputGroup>
              </FormControl>

              {/* Submit button */}
              <Button colorScheme="primary" type="submit" isLoading={isSubmitting}>
                {t('submitButton')}
              </Button>
            </>
          )}
        </Stack>
      </form>
    </AuthFormWrapper>
  );
}

export default SignupForm;
