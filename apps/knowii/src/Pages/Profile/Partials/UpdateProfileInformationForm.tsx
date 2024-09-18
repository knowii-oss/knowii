import {
  DEFAULT_TOAST_POSITION,
  CurrentUser,
  USER_PROFILE_INFORMATION_UPDATE_URL,
  useTypedPage,
  knowiiApiClient,
  useDebounce,
  usernameSchema,
} from '@knowii/common';
import { Link, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { Button } from 'primereact/button';
import FormSection from '@/Components/FormSection';
import classNames from 'classnames';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaPassport, FaUser } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Toast } from 'primereact/toast';

interface Props {
  user: CurrentUser;
}

interface UpdateProfileInformationFormData {
  name: string;
  email: string;
  username: string;
  photo: File | null;
}

export default function UpdateProfileInformationForm(props: Props) {
  const route = useRoute();
  const page = useTypedPage();

  const toastRef = useRef<Toast | null>(null);

  const [verificationLinkSent, setVerificationLinkSent] = useState(false);

  const form = useForm<UpdateProfileInformationFormData>({
    name: props.user.name,
    email: props.user.email,
    username: props.user.username,
    photo: null as File | null,
  });

  const [checkingUsernameAvailability, setCheckingUsernameAvailability] = useState(false);
  const [isUsernameAvailable, setIsUsernameAvailable] = useState<boolean>(false);
  // TODO extract debounce value to a constant
  const usernameToCheck = useDebounce(form.data.username, 200);

  const lastAbortController = useRef<AbortController | null>();

  const verifyUsernameAvailability = async (abortController: AbortController) => {
    if (form.data.username === page.props.auth.user.username) {
      // No need to check if the username is the same as the current one
      setIsUsernameAvailable(true);
      form.clearErrors('username');
      return;
    }

    const usernameValidationResults = usernameSchema.safeParse(form.data.username);
    if (!usernameValidationResults.success) {
      console.log('The username is invalid.');
      form.setError('username', usernameValidationResults.error.errors[0].message);
      return;
    }

    setCheckingUsernameAvailability(true);

    const checkResult = await checkIfUsernameIsAvailable(form.data.username, abortController.signal);
    setIsUsernameAvailable(checkResult);

    if (!checkResult) {
      console.log('The username is already taken.');
      form.errors.username = 'The username is already taken.';
    } else {
      console.log('The username is available');
      form.clearErrors('username');
    }

    if (lastAbortController.current) {
      lastAbortController.current = null;
    }

    setCheckingUsernameAvailability(false);
  };

  const checkIfUsernameIsAvailable = async (username: string, signal?: AbortSignal): Promise<boolean> => {
    console.log('Checking username availability');

    const response = await knowiiApiClient.users.isUsernameAvailable(
      {
        usernameToCheck: username,
      },
      signal,
    );

    if ('success' === response.type && !response.errors && response.data) {
      const isUsernameAvailable = response.data.isUsernameAvailable;
      return isUsernameAvailable;
    }

    return false;
  };

  useEffect(() => {
    // When a new request is going to be issued,
    // the first thing to do is cancel the previous one
    lastAbortController.current?.abort();

    // Create new AbortController for the new request and store it in the ref
    const currentAbortController = new AbortController();
    lastAbortController.current = currentAbortController;

    verifyUsernameAvailability(currentAbortController);
  }, [usernameToCheck]);

  const updateProfileInformation: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route(USER_PROFILE_INFORMATION_UPDATE_URL), {
      errorBag: 'updateProfileInformation',
      preserveScroll: true,
      onSuccess: () => {
        toastRef.current?.show({ severity: 'success', summary: 'Success', detail: 'Profile changed successfully.' });
      },
    });
  };

  const sendEmailVerification = () => {
    setVerificationLinkSent(true);
  };

  return (
    <FormSection
      onSubmit={updateProfileInformation}
      title={'Profile Information'}
      description={`Update your account's profile information and email address.`}
      renderActions={() => (
        <>
          <Toast position={DEFAULT_TOAST_POSITION} ref={toastRef} />

          <div className="flex flex-col sm:flex-row gap-2 w-full items-center sm:w-auto sm:justify-end">
            <Button
              type="button"
              onClick={() => {
                form.reset();
                setIsUsernameAvailable(true);
              }}
              severity="secondary"
              className={classNames({ 'opacity-25': form.processing })}
            >
              Reset
            </Button>
            <Button
              className={classNames({ 'opacity-25': form.processing })}
              disabled={form.processing || checkingUsernameAvailability || form.errors.username}
            >
              Save
            </Button>
          </div>
        </>
      )}
    >
      {/* Name */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="name">Name</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaUser />
          </span>
          <InputText
            id="name"
            type="name"
            className="mt-1 block w-full"
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            autoComplete="name"
            required
            disabled={form.processing}
          />
        </div>
        <InputError className="mt-2" message={form.errors.name} />
      </div>

      {/* Email */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="email">Email</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaAt />
          </span>
          <InputText
            id="email"
            type="email"
            className="mt-1 block w-full"
            value={form.data.email}
            onChange={(e) => form.setData('email', e.target.value)}
            autoComplete="username"
            required
            disabled={form.processing}
          />
        </div>
        <InputError className="mt-2" message={form.errors.email} />

        {page.props.jetstream.hasEmailVerification && props.user.email_verified_at === null ? (
          <div>
            <p className="text-sm mt-2">
              <span className="text-red">Your email address is unverified.&nbsp;</span>
              <Link
                href={route('verification.send')}
                method="post"
                as="button"
                className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                onClick={sendEmailVerification}
              >
                Click here to re-send the verification email.
              </Link>
            </p>
            {verificationLinkSent && (
              <div className="mt-2 font-medium text-sm text-green-600">A new verification link has been sent to your email address.</div>
            )}
          </div>
        ) : null}
      </div>

      {/* Username */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="username">Username</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaPassport />
          </span>
          <InputText
            id="username"
            type="username"
            className="mt-1 block w-full"
            value={form.data.username}
            onChange={(e) => form.setData('username', e.target.value)}
            autoComplete="username"
            disabled={form.processing}
            required
          />
        </div>
        <InputError className="mt-2" message={form.errors.username} />
      </div>
    </FormSection>
  );
}
