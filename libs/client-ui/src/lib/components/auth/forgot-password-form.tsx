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
  Link,
  Stack,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import NextLink from 'next/link';
import { FormEvent } from 'react';
import { useForm } from 'react-hook-form';
import { FaAt } from 'react-icons/fa';
import { useAuthRedirectUrl } from '@knowii/client';
import { AuthFormWrapper } from './auth-form-wrapper';
import { useTranslations } from 'next-intl';
import { CALLBACK_URL, RESET_PASSWORD_URL, SIGN_IN_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ForgotPasswordFormProps {}

export function ForgotPasswordForm(_props: ForgotPasswordFormProps) {
  const supabaseClient = useSupabaseClient();

  const t = useTranslations('forgotPasswordForm');

  const redirectTo = useAuthRedirectUrl(`${CALLBACK_URL}?redirectAfterSignin=${encodeURIComponent(RESET_PASSWORD_URL)}`);
  const { register, handleSubmit, formState, setError, clearErrors } = useForm<{ email: string; serverError?: void }>();
  const { isSubmitting, isSubmitted, isSubmitSuccessful } = formState;

  const onSubmit = (e: FormEvent) => {
    clearErrors('serverError');
    handleSubmit(async ({ email }) => {
      const { error } = await supabaseClient.auth.resetPasswordForEmail(email, { redirectTo });

      if (error) {
        setError('serverError', { type: 'manual', message: error.message });
      }
    })(e);
  };

  return (
    <AuthFormWrapper title={t('title')}>
      <form onSubmit={onSubmit}>
        <Stack spacing={5}>
          {isSubmitted &&
            (isSubmitSuccessful ? (
              <Alert status="success" rounded="lg">
                <AlertIcon />
                <AlertTitle>{t('linkSent')}</AlertTitle>
              </Alert>
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
                    required
                    isRequired
                    type="email"
                    autoComplete="email"
                    {...register('email', {
                      required: true,
                    })}
                  />
                </InputGroup>
              </FormControl>

              {/* Submit button */}
              <Button colorScheme="primary" type="submit" isLoading={isSubmitting}>
                {t('sendLink')}
              </Button>
            </>
          )}

          <Link as={NextLink} href={SIGN_IN_URL} color="primary.500">
            &larr; {t('backToSignIn')}
          </Link>
        </Stack>
      </form>
    </AuthFormWrapper>
  );
}

export default ForgotPasswordForm;
