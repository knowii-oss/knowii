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
  Stack,
  useToast,
} from '@chakra-ui/react';
import { useSupabaseClient } from '@supabase/auth-helpers-react';
import { useRouter } from 'next/router';
import { FormEvent, useCallback, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FaEye, FaEyeSlash, FaLock } from 'react-icons/fa';
import { AuthFormWrapper } from './auth-form-wrapper';
import { useTranslations } from 'next-intl';
import { APP_BASE_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ResetPasswordFormProps {}

export function ResetPasswordForm(_props: ResetPasswordFormProps) {
  const t = useTranslations('resetPasswordForm');

  const router = useRouter();
  const supabaseClient = useSupabaseClient();
  const toast = useToast();
  const { register, handleSubmit, formState, setError, clearErrors } = useForm<{
    password: string;
    serverError?: void;
  }>();
  const { isSubmitting, isSubmitted, isSubmitSuccessful } = formState;
  const [isPasswordVisible, setPasswordVisible] = useState(false);

  const onSubmit = (e: FormEvent) => {
    clearErrors('serverError');
    handleSubmit(async ({ password }) => {
      const { error } = await supabaseClient.auth.updateUser({ password });

      if (error) {
        setError('serverError', { message: error.message });
        return;
      }

      await router.push(APP_BASE_URL);

      toast({
        status: 'success',
        title: t('successMessage'),
      });
    })(e);
  };

  const togglePassword = useCallback(() => setPasswordVisible(!isPasswordVisible), [isPasswordVisible]);

  return (
    <AuthFormWrapper title={t('title')}>
      <form onSubmit={onSubmit}>
        <Stack spacing={5}>
          {isSubmitted && !isSubmitSuccessful && (
            <Alert status="error" rounded="lg">
              <AlertIcon />
              <AlertTitle>{t('errorMessage')}</AlertTitle>
            </Alert>
          )}

          {/* Password field */}
          <FormControl>
            <FormLabel>{t('fields.newPassword')}</FormLabel>
            <InputGroup>
              <InputLeftElement color="gray.300">
                <FaLock />
              </InputLeftElement>
              <Input
                required
                isRequired
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
        </Stack>
      </form>
    </AuthFormWrapper>
  );
}

export default ResetPasswordForm;
