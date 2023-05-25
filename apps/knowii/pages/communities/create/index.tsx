import {
  Alert,
  AlertIcon,
  AlertTitle,
  Box,
  Button,
  Container,
  FormControl,
  FormErrorMessage,
  FormLabel,
  Input,
  InputGroup,
  InputRightElement,
  Stack,
  VStack,
} from '@chakra-ui/react';
import { GetServerSideProps } from 'next';
import { useTranslations } from 'next-intl';
import Layout from '../../../components/layout/layout';
import { PageHeader, useDebounce } from '@knowii/client-ui';
import { i18nConfig } from '../../../../../i18n.config.mjs';
import { CustomPageProps } from '../../_app';
import { SubmitHandler, useForm } from 'react-hook-form';
import { ClipboardEvent, KeyboardEvent, useState, useRef, useEffect } from 'react';
import { FaCheckCircle, FaClock, FaTimesCircle } from 'react-icons/fa';
import {
  allowedCommunityNameCharactersRegex,
  API_COMMUNITY_NAME_AVAILABILITY_CHECK,
  forbiddenCommunityNameCharactersRegex,
  IsCommunityNameAvailableRequest,
  maxLengthCommunityName,
  minLengthCommunityName,
} from '@knowii/common';

//import { useRouter } from 'next/router';
//import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
//import { Database, HOME_URL } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface CreateNewCommunityPageProps {}

// https://github.com/DeveloPassion/knowii/issues/238
// FIXME add link to this page on the communities page (only when the user is logged in!)
// FIXME autofill the community slug based on the entered name
// FIXME ensure availability of the community slug

// name
// slug
// description
// visibility: public and read-only (only if paid for right away)

export const getServerSideProps: GetServerSideProps<Partial<CustomPageProps>> = async (ctx) => {
  const locale = ctx.locale ? ctx.locale : i18nConfig.i18n.defaultLocale;

  const messages = (await import(`../../../../../libs/common/src/lib/messages/${locale}.json`)).default;

  //const supabaseClient = createServerSupabaseClient<Database>(ctx);
  //const { data: session } = await supabaseClient.auth.getSession();

  const retVal: {
    props: Partial<CustomPageProps & CreateNewCommunityPageProps>;
  } = {
    props: {
      messages,
      // Note that when `now` is passed to the app, you need to make sure the
      // value is updated from time to time, so relative times are updated. See
      // https://next-intl-docs.vercel.app/docs/usage/configuration#global-now-value
      now: new Date().getTime(),
    },
  };

  return retVal;
};

interface CreateNewCommunityFormData {
  name: string;
  serverError?: void;
}

export function CreateNewCommunityPage(_props: CreateNewCommunityPageProps) {
  const t = useTranslations('createNewCommunityPage');
  //const router = useRouter();
  //const supabaseClient = useSupabaseClient();

  const {
    register,
    handleSubmit,
    // WARNING it is mandatory to extract these values like this. Otherwise, they are not updated
    // This is because formState is a proxy and we need to read from it to subscribe to data changes
    formState: { errors, isSubmitting, isSubmitted, isSubmitSuccessful, isValid, touchedFields },
    setError,
    clearErrors,
  } = useForm<CreateNewCommunityFormData>({
    mode: 'all', // WARNING: Impact on performance when set to all vs onBlur (many re-renders)
    defaultValues: {
      name: '',
    },
  });

  const [checkingNameAvailability, setCheckingNameAvailability] = useState(false);
  const [isNameAvailable, setIsNameAvailable] = useState(false);

  const onSubmit: SubmitHandler<CreateNewCommunityFormData> = ({ name }) => {
    clearErrors('serverError');
    // FIXME implement
    console.log('Name: ', name);
    //await supabaseClient.

    // FIXME remove dummy error handling
    const error = false;
    if (error) {
      setError('serverError', { message: '' });
      return;
    }

    // FIXME if everything is okay, redirect to the new community's page
    //await router.push(redirectPath);
  };

  const filterInvalidNameCharacters = (e: KeyboardEvent) => {
    if (forbiddenCommunityNameCharactersRegex.test(e.key)) {
      e.preventDefault();
    }
  };

  const filterInvalidNameCharactersFromPaste = (e: ClipboardEvent<HTMLInputElement>) => {
    if (e.clipboardData && forbiddenCommunityNameCharactersRegex.test(e.clipboardData.getData('Text'))) {
      e.preventDefault();
    }
  };

  const [name, setName] = useState('');
  const nameToCheck = useDebounce(name, 500);

  const lastAbortController = useRef<AbortController | null>();

  const verifyNameAvailability = async (abortController: AbortController) => {
    setCheckingNameAvailability(true);

    setIsNameAvailable(await checkIfNameIsAvailable(name, abortController.signal));

    if (lastAbortController.current) {
      lastAbortController.current = null;
    }

    setCheckingNameAvailability(false);
  };

  useEffect(() => {
    // When a new request is going to be issued,
    // the first thing to do is cancel the previous one
    lastAbortController.current?.abort();

    // Create new AbortController for the new request and store it in the ref
    const currentAbortController = new AbortController();
    lastAbortController.current = currentAbortController;

    verifyNameAvailability(currentAbortController);
  }, [nameToCheck]);

  const checkIfNameIsAvailable = async (name: string, signal?: AbortSignal): Promise<boolean> => {
    if (name.trim().length < minLengthCommunityName) {
      return false;
    }

    if (forbiddenCommunityNameCharactersRegex.test(name)) {
      return false;
    }

    console.log('Checking community name availability');

    const requestBody: IsCommunityNameAvailableRequest = {
      nameToCheck: name,
    };

    try {
      const response = await fetch(API_COMMUNITY_NAME_AVAILABILITY_CHECK, {
        signal,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestBody),
      });

      //console.log('Response: ', response);

      if (response.ok) {
        const responseBody = await response.json();

        if (responseBody.isNameAvailable) {
          console.log('Name is available');
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
    <Layout
      customMeta={{
        title: t('title'),
      }}
    >
      <PageHeader title={t('title')} description={t('description')} />
      <Box px={4} py={12}>
        <Container maxW="5xl">
          <VStack spacing={4} align="stretch">
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
                        <Input
                          required
                          {...register('name', {
                            required: t('validationError.name.required'),
                            minLength: {
                              value: minLengthCommunityName,
                              message: t('validationError.name.tooShort'),
                            },
                            maxLength: {
                              value: maxLengthCommunityName,
                              message: t('validationError.name.tooLong', { length: maxLengthCommunityName }),
                            },
                            pattern: {
                              value: allowedCommunityNameCharactersRegex,
                              message: t('validationError.name.forbiddenCharacters'),
                            },
                            onChange: (event) => setName(event.target.value),
                          })}
                          onKeyDown={(e) => filterInvalidNameCharacters(e)}
                          onPaste={(e) => filterInvalidNameCharactersFromPaste(e)}
                        />
                        <InputRightElement>
                          {
                            /* Only check the name availability when the field has been touched */
                            !touchedFields.name ? (
                              ''
                            ) : checkingNameAvailability ? (
                              <FaClock className="text-gray-500" />
                            ) : isNameAvailable ? (
                              <FaCheckCircle className="text-green-500" />
                            ) : (
                              <FaTimesCircle className="text-red-500" />
                            )
                          }
                        </InputRightElement>
                      </InputGroup>
                      <FormErrorMessage>{errors.name && errors.name.message}</FormErrorMessage>
                    </FormControl>

                    {/* Submit button */}
                    <Button colorScheme="primary" type="submit" isLoading={isSubmitting} isDisabled={!isValid || !isNameAvailable}>
                      {t('submitButton')}
                    </Button>
                  </>
                )}
              </Stack>
            </form>
          </VStack>
        </Container>
      </Box>
    </Layout>
  );
}

export default CreateNewCommunityPage;
