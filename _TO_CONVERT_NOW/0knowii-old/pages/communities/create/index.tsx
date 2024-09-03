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
  Textarea,
  useToast,
  VStack,
} from '@chakra-ui/react';
import { useTranslations } from 'next-intl';
import Layout from '../../../components/layout/layout';
import { PageHeader, useDebounce } from '@knowii/client-ui';
import { SubmitHandler, useForm } from 'react-hook-form';
import { ClipboardEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import { FaCheckCircle, FaClock, FaTimesCircle } from 'react-icons/fa';
import {
  allowedCommunityNameCharactersRegex,
  API_COMMUNITY_NAME_AVAILABILITY_CHECK,
  API_CREATE_NEW_COMMUNITY,
  COMMUNITY_BASE_URL,
  CommunitiesSchema,
  CreateCommunityRequest,
  CreateCommunityResponse,
  forbiddenCommunityNameCharactersRegex,
  IsCommunityNameAvailableRequest,
  IsCommunityNameAvailableResponse,
  maxLengthCommunityName,
  minLengthCommunityName,
} from '@knowii/common';
import { z } from 'zod';

import { useRouter } from 'next/router';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface CreateNewCommunityPageProps {}

const createNewCommunityFormSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
});

type CreateNewCommunityFormData = z.infer<typeof createNewCommunityFormSchema> & {
  serverError?: void;
};

export function CreateNewCommunityPage(_props: CreateNewCommunityPageProps) {
  const t = useTranslations('createNewCommunityPage');
  const router = useRouter();
  const toast = useToast();

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
      description: '',
    },
  });

  const [checkingNameAvailability, setCheckingNameAvailability] = useState(false);
  const [isNameAvailable, setIsNameAvailable] = useState(false);

  const onSubmit: SubmitHandler<CreateNewCommunityFormData> = async ({ name, description }) => {
    clearErrors('serverError');

    const requestBody: CreateCommunityRequest = {
      name,
      description,
    };

    const response = await fetch(API_CREATE_NEW_COMMUNITY, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody),
    });

    console.log('Response: ', response);

    const responseBody: CreateCommunityResponse = await response.json();

    if (!response.ok || !responseBody.data) {
      console.log('Response body: ', responseBody);
      // TODO improve error handling
      if (responseBody.errors) {
        setError('serverError', { message: responseBody.errors.title });
        return;
      }
      setError('serverError', { message: '' });
      return;
    }

    const redirectPath = `${COMMUNITY_BASE_URL}/${responseBody.data.slug}`;
    console.log('Redirecting to: ', redirectPath);

    await router.push(redirectPath);

    toast({
      status: 'success',
      title: t('successMessage'),
    });
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

      if (response.ok) {
        const responseBody: IsCommunityNameAvailableResponse = await response.json();

        if (!responseBody.data) {
          return false;
        }

        if (responseBody.data.isNameAvailable) {
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
                {isSubmitted && !isSubmitSuccessful && (
                  <Alert status="error" rounded="lg">
                    <AlertIcon />
                    <AlertTitle>{t('errorMessage')}</AlertTitle>
                  </Alert>
                )}

                {(!isSubmitted || !isSubmitSuccessful) && (
                  <>
                    {/* Name field */}
                    <FormControl isInvalid={!!errors.name}>
                      <FormLabel>{t('fields.name')}</FormLabel>
                      <InputGroup>
                        <Input
                          required
                          isRequired
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

                    {/* Description field */}
                    <FormControl isInvalid={!!errors.description}>
                      <FormLabel>{t('fields.description')}</FormLabel>
                      <InputGroup>
                        <Textarea {...register('description', {})} />
                      </InputGroup>
                      <FormErrorMessage>{errors.description && errors.description.message}</FormErrorMessage>
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
