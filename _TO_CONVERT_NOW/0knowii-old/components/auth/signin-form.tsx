import { FaAt, FaLock } from 'react-icons/fa';
import { APP_BASE_URL, CALLBACK_URL, Database, FORGOT_PASSWORD_URL, SIGN_UP_URL } from '@knowii/common';
import { Loader } from '../common/loader';
import { SigninMode, SigninModeSwitch } from './signin-mode-switch';

export function SigninForm() {
  return (
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
                  require
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
                  <Input required isRequired type="password" autoComplete="password" {...register('password', { required: true })} />
                </InputGroup>
                <FormHelperText>
                  <Link as={NextLink} href={FORGOT_PASSWORD_URL} color="primary.500">
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
  );
}

export default SigninForm;
