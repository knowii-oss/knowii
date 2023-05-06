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
import { ClipboardEvent, FormEvent, KeyboardEvent, useCallback, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FaAt, FaCheckCircle, FaClock, FaEye, FaEyeSlash, FaLock, FaTimesCircle, FaUserAlt } from 'react-icons/fa';
import { allowedUsernameCharactersRegex, maxLengthUsername, minLengthUsername, redirectPath, SIGN_IN_URL } from '@knowii/common';
import { useAuthRedirectUrl } from '@knowii/client';
import { AuthFormWrapper } from './auth-form-wrapper';
import { useTranslations } from 'next-intl';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface SignupFormProps {
  checkingUsernameAvailability: boolean;
  isUsernameAvailable: boolean;
  checkUsernameAvailability: (username: string) => void;
}

interface SignupFormData {
  email: string;
  password: string;
  name: string;
  username: string;
  serverError?: void;
}

export function SignupForm(props: SignupFormProps) {
  const t = useTranslations('signupForm');
  const supabaseClient = useSupabaseClient();
  const router = useRouter();
  const redirectTo = useAuthRedirectUrl();
  const { register, handleSubmit, formState, setError, clearErrors } = useForm<SignupFormData>({
    defaultValues: {
      email: '',
      name: '',
      password: '',
      username: '',
    },
  });

  // WARNING it is mandatory to extract these values like this. Otherwise they are not updated
  const { isSubmitting, isSubmitted, isSubmitSuccessful, isValid } = formState;
  const [isPasswordVisible, setPasswordVisible] = useState(false);

  const onSubmit = (e: FormEvent) => {
    clearErrors('serverError');
    handleSubmit(async ({ email, username, password, name }) => {
      const {
        data: { session, user: newUser },
        error,
      } = await supabaseClient.auth.signUp({
        email,
        password,
        options: {
          data: {
            // WARNING: Those kay names are VERY sensitive
            // They are used by the triggers defined in supabase-db-seed.sql
            // 'name' matches the field name used by GitHub (OAuth)
            name,
            email,
            // 'user_name' matches the field name used by GitHub (OAuth)
            user_name: username,
            // TODO implement support for avatar url
            avatar_url: '',
          },
          emailRedirectTo: redirectTo,
        },
      });

      if (error || !newUser) {
        setError('serverError', { message: error?.message });
        return;
      }

      // if email confirmations are enabled, the user will have to confirm their email before they can sign in
      // Reference: https://supabase.com/docs/reference/dart/auth-signup
      if (!session) {
        return;
      }

      // if email confirmations are disabled, the user will be signed in automatically and redirected
      await router.push(redirectPath);
    })(e);
  };

  const togglePassword = useCallback(() => setPasswordVisible(!isPasswordVisible), [isPasswordVisible]);

  const filterInvalidUsernameCharacters = (e: KeyboardEvent) => {
    if (allowedUsernameCharactersRegex.test(e.key)) {
      e.preventDefault();
    }
  };

  const filterInvalidUsernameCharactersFromPaste = (e: ClipboardEvent<HTMLInputElement>) => {
    if (e.clipboardData && allowedUsernameCharactersRegex.test(e.clipboardData.getData('Text'))) {
      e.preventDefault();
    }
  };

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
                  <Input
                    // Reference: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
                    autoComplete="name"
                    {...register('name', { required: true })}
                  />
                </InputGroup>
              </FormControl>

              {/* Email field */}
              <FormControl>
                <FormLabel>{t('fields.email')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaAt />
                  </InputLeftElement>

                  <Input
                    required
                    type="email"
                    // Reference: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
                    autoComplete="email"
                    {...register('email', { required: true })}
                  />
                </InputGroup>
              </FormControl>

              {/* Username field */}
              <FormControl>
                <FormLabel>{t('fields.username')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaUserAlt />
                  </InputLeftElement>
                  <Input
                    required
                    {...register('username', {
                      required: 'The username is mandatory',
                      minLength: {
                        value: minLengthUsername,
                        message: `The username must at least be ${maxLengthUsername} characters long`,
                      },
                      maxLength: {
                        value: maxLengthUsername,
                        message: `The username cannot be longer than ${maxLengthUsername} characters`,
                      },
                      pattern: {
                        value: allowedUsernameCharactersRegex,
                        message: "The username can only contain the following characters: letters from a to z, numbers, '_' and '-'",
                      },
                    })}
                    onChange={(event) => props.checkUsernameAvailability(event.target.value)}
                    onKeyDown={(e) => filterInvalidUsernameCharacters(e)}
                    onPaste={(e) => filterInvalidUsernameCharactersFromPaste(e)}
                  />
                  <InputRightElement>
                    {
                      /* Only check the username availability when the field has been touched */
                      !formState.touchedFields.username ? (
                        ''
                      ) : props.checkingUsernameAvailability ? (
                        <FaClock className="text-gray-500" />
                      ) : props.isUsernameAvailable ? (
                        <FaCheckCircle className="text-green-500" />
                      ) : (
                        <FaTimesCircle className="text-red-500" />
                      )
                    }
                  </InputRightElement>
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
                    // Reference: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
                    autoComplete="new-password"
                    {...register('password', { required: true })}
                  />
                  <InputRightElement color="primary.500" as="button" type="button" onClick={togglePassword}>
                    {isPasswordVisible ? <FaEyeSlash /> : <FaEye />}
                  </InputRightElement>
                </InputGroup>
              </FormControl>

              {/* Submit button */}
              <Button colorScheme="primary" type="submit" isLoading={isSubmitting} isDisabled={!props.isUsernameAvailable || !isValid}>
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
