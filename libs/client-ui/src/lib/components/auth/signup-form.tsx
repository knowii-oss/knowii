import {
  Alert,
  AlertIcon,
  AlertTitle,
  Button,
  FormControl,
  FormErrorMessage,
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
import { ClipboardEvent, KeyboardEvent, useCallback, useState } from 'react';
import { SubmitHandler, useForm } from 'react-hook-form';
import { FaAt, FaCheckCircle, FaClock, FaEye, FaEyeSlash, FaLock, FaTimesCircle, FaUserAlt } from 'react-icons/fa';
import {
  allowedUsernameCharactersRegex,
  forbiddenUsernameCharactersRegex,
  maxLengthUsername,
  minLengthUsername,
  redirectPath,
  SIGN_IN_URL,
} from '@knowii/common';
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
  const {
    register,
    handleSubmit,
    // WARNING it is mandatory to extract these values like this. Otherwise, they are not updated
    // This is because formState is a proxy and we need to read from it to subscribe to data changes
    formState: { errors, isSubmitting, isSubmitted, isSubmitSuccessful, isValid, touchedFields },
    setError,
    clearErrors,
  } = useForm<SignupFormData>({
    mode: 'all', // WARNING: Impact on performance when set to all vs onBlur (many re-renders)
    defaultValues: {
      name: '',
      email: '',
      username: '',
      password: '',
    },
  });

  const [isPasswordVisible, setPasswordVisible] = useState(false);

  const onSubmit: SubmitHandler<SignupFormData> = async ({ email, username, password, name }) => {
    clearErrors('serverError');

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
  };

  const togglePassword = useCallback(() => setPasswordVisible(!isPasswordVisible), [isPasswordVisible]);

  const filterInvalidUsernameCharacters = (e: KeyboardEvent) => {
    if (forbiddenUsernameCharactersRegex.test(e.key)) {
      e.preventDefault();
    }
  };

  const filterInvalidUsernameCharactersFromPaste = (e: ClipboardEvent<HTMLInputElement>) => {
    if (e.clipboardData && forbiddenUsernameCharactersRegex.test(e.clipboardData.getData('Text'))) {
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
      <form onSubmit={handleSubmit(onSubmit)}>
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
              <FormControl isInvalid={!!errors.name}>
                <FormLabel>{t('fields.name')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaUserAlt />
                  </InputLeftElement>
                  <Input
                    required
                    // Reference: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
                    autoComplete="name"
                    {...register('name', { required: t('validationError.name.required') })}
                  />
                </InputGroup>
                <FormErrorMessage>{errors.name && errors.name.message}</FormErrorMessage>
              </FormControl>

              {/* Email field */}
              <FormControl isInvalid={!!errors.email}>
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
                    {...register('email', { required: t('validationError.email.required') })}
                  />
                </InputGroup>
                <FormErrorMessage>{errors.email && errors.email.message}</FormErrorMessage>
              </FormControl>

              {/* Username field */}
              <FormControl isInvalid={!!errors.username}>
                <FormLabel>{t('fields.username')}</FormLabel>
                <InputGroup>
                  <InputLeftElement color="gray.300">
                    <FaUserAlt />
                  </InputLeftElement>
                  <Input
                    required
                    {...register('username', {
                      required: t('validationError.username.required'),
                      minLength: {
                        value: minLengthUsername,
                        message: t('validationError.username.tooShort'),
                      },
                      maxLength: {
                        value: maxLengthUsername,
                        // WARNING: The message parameter name MUST correspond in the translation file
                        message: t('validationError.username.tooLong', { length: maxLengthUsername }),
                      },
                      pattern: {
                        value: allowedUsernameCharactersRegex,
                        message: t('validationError.username.forbiddenCharacters'),
                      },
                      onChange: (event) => props.checkUsernameAvailability(event.target.value),
                    })}
                    onKeyDown={(e) => filterInvalidUsernameCharacters(e)}
                    onPaste={(e) => filterInvalidUsernameCharactersFromPaste(e)}
                  />
                  <InputRightElement>
                    {
                      /* Only check the username availability when the field has been touched */
                      !touchedFields.username ? (
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
                <FormErrorMessage>{errors.username && errors.username.message}</FormErrorMessage>
              </FormControl>

              {/* Password field */}
              <FormControl isInvalid={!!errors.password}>
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
                    {...register('password', { required: t('validationError.password.required') })}
                  />
                  <InputRightElement color="primary.500" as="button" type="button" onClick={togglePassword}>
                    {isPasswordVisible ? <FaEyeSlash /> : <FaEye />}
                  </InputRightElement>
                </InputGroup>
                <FormErrorMessage>{errors.password && errors.password.message}</FormErrorMessage>
              </FormControl>

              {/* Submit button */}
              <Button colorScheme="primary" type="submit" isLoading={isSubmitting} isDisabled={!isValid || !props.isUsernameAvailable}>
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
