import type { CurrentUser, UserProfile } from '@knowii/common';
import {
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  SOCIAL_MEDIA_LINK_ICONS,
  SOCIAL_MEDIA_LINK_NAMES,
  SOCIAL_MEDIA_LINK_PROPERTIES,
  useAppData,
  useDebounce,
  USER_PROFILE_INFORMATION_UPDATE_URL,
  usernameSchema,
  useTypedPage,
  MAX_LENGTH_USER_LOCATION,
  MAX_LENGTH_USER_BIO,
  MAX_LENGTH_USER_PHONE,
  USER_PHONE_REGEX,
  USER_PROFILE_BIO_TEXTAREA_ROWS,
  EMAIL_VERIFICATION_URL,
} from '@knowii/common';
import { Link, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useEffect, useState } from 'react';
import FormSection from '@/Components/FormSection';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaBookReader, FaMapMarkerAlt, FaPassport, FaPhone, FaUser } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import { Button, Collapsible, Textarea, TextInput } from '@/Components/ui';

interface Props {
  user: CurrentUser;
}

type UpdateProfileInformationFormData = { photo: File | null } & Omit<
  CurrentUser,
  'created_at' | 'updated_at' | 'email_verified_at' | 'cuid' | 'two_factor_enabled' | 'two_factor_confirmed_at'
> &
  Omit<UserProfile, 'username'>;

export default function UpdateProfileInformationForm(props: Props) {
  const route = useRoute();
  const page = useTypedPage();

  const userEmailNotVerified = page.props.jetstream.hasEmailVerification && props.user.email_verified_at === null;

  const { showToast } = useAppData();

  const [verificationLinkSent, setVerificationLinkSent] = useState(false);

  // Some fields of the user profile are 1:1 copies of the user account, so they need to be ignored here
  const { name: nameToIgnore, username: usernameToIgnore, ...userProfileFieldsToInclude } = page.props.userProfile;

  const form = useForm<UpdateProfileInformationFormData>({
    ...props.user,
    ...userProfileFieldsToInclude,
    photo: null as File | null,
  });

  const [checkingUsernameAvailability, setCheckingUsernameAvailability] = useState(false);
  const usernameToCheck = useDebounce(form.data.username, 200);

  /**
   * Synchronous username checks, run when the username changes (event handler).
   * Returns true if the username must be checked against the server.
   */
  const validateUsernameLocally = (username: string): boolean => {
    if (props.user && username === props.user.username) {
      // No need to check if the username is the same as the current one
      form.clearErrors('username');
      setCheckingUsernameAvailability(false);
      return false;
    }

    const usernameValidationResults = usernameSchema.safeParse(username);
    if (!usernameValidationResults.success) {
      form.setError('username', usernameValidationResults.error.issues[0]?.message ?? 'Invalid username');
      setCheckingUsernameAvailability(false);
      return false;
    }

    setCheckingUsernameAvailability(true);
    return true;
  };

  const checkIfUsernameIsAvailable = async (username: string, signal?: AbortSignal): Promise<boolean> => {
    const response = await knowiiApiClient.users.isUsernameAvailable(
      {
        usernameToCheck: username,
      },
      signal,
    );

    // Checking username availability takes time ;-)
    await sleep(MIN_ACTION_TIME);

    if ('success' === response.type && !response.errors && response.data) {
      const isUsernameAvailable = response.data.isUsernameAvailable;
      return isUsernameAvailable;
    }

    return false;
  };

  // Check the (debounced) username against the server. State is only updated once the request completes.
  useEffect(() => {
    if (props.user && usernameToCheck === props.user.username) {
      return;
    }
    if (!usernameSchema.safeParse(usernameToCheck).success) {
      return;
    }

    const abortController = new AbortController();

    void checkIfUsernameIsAvailable(usernameToCheck, abortController.signal)
      .then((isAvailable) => {
        if (abortController.signal.aborted) {
          // A newer username is being checked
          return;
        }
        if (isAvailable) {
          form.clearErrors('username');
        } else {
          form.setError('username', 'The username is already taken.');
        }
        setCheckingUsernameAvailability(false);
      })
      .catch(() => {
        if (!abortController.signal.aborted) {
          setCheckingUsernameAvailability(false);
        }
      });

    // Cancel the request if the username changes before it completes
    return () => abortController.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [usernameToCheck]);

  const updateProfileInformation: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route(USER_PROFILE_INFORMATION_UPDATE_URL), {
      preserveScroll: true,
      onSuccess: () => {
        showToast({ severity: 'success', summary: 'Success', detail: 'Profile updated successfully.' });
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
      description={
        <>
          <span>
            Update your account's profile information. Note that Apart from your username, all the information will be publicly visible to
            other Knowii users.
          </span>
          {userEmailNotVerified && (
            <div role="alert" className="mt-2 font-bold text-danger-text">
              WARNING: You will not be able to edit your profile until your email address has been verified.
            </div>
          )}
        </>
      }
      renderActions={() => (
        <div className="flex flex-col sm:flex-row gap-2 w-full items-center sm:w-auto sm:justify-end">
          <Button
            variant="secondary"
            onClick={() => {
              form.reset();
            }}
            disabled={form.processing}
          >
            Reset
          </Button>
          <Button type="submit" loading={form.processing} disabled={form.hasErrors || checkingUsernameAvailability}>
            Save
          </Button>
        </div>
      )}
    >
      {/* Name */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="name">Name</InputLabel>
        <TextInput
          icon={<FaUser />}
          wrapperClassName="mt-1"
          id="name"
          type="text"
          value={form.data.name}
          onChange={(e) => form.setData('name', e.target.value)}
          autoComplete="name"
          required
          disabled={form.processing || userEmailNotVerified}
        />
        <InputError className="mt-2" message={form.errors.name} />
      </div>

      {/* Email */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="email">Email</InputLabel>
        <TextInput
          icon={<FaAt />}
          wrapperClassName="mt-1"
          id="email"
          type="email"
          value={form.data.email}
          onChange={(e) => form.setData('email', e.target.value)}
          autoComplete="username"
          required
          disabled={form.processing || userEmailNotVerified}
        />
        <InputError className="mt-2" message={form.errors.email} />

        {userEmailNotVerified ? (
          <div>
            <p className="text-sm mt-2">
              <span className="text-danger-text">Your email address is unverified.&nbsp;</span>
              <Link
                href={route(EMAIL_VERIFICATION_URL)}
                method="post"
                as="button"
                className="simple-link cursor-pointer"
                onClick={sendEmailVerification}
              >
                Click here to re-send the verification email.
              </Link>
            </p>
            {verificationLinkSent && (
              <div role="status" className="mt-2 text-sm font-medium text-success">
                A new verification link has been sent to your email address.
              </div>
            )}
          </div>
        ) : null}
      </div>

      {/* Username */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="username">Username</InputLabel>
        <TextInput
          icon={<FaPassport />}
          wrapperClassName="mt-1"
          id="username"
          type="text"
          value={form.data.username}
          onChange={(e) => {
            form.setData('username', e.target.value);
            validateUsernameLocally(e.target.value);
          }}
          autoComplete="username"
          disabled={form.processing || userEmailNotVerified}
          required
        />
        <InputError className="mt-2" message={form.errors.username} />
      </div>

      {/* Bio */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="bio">Bio</InputLabel>
        <Textarea
          icon={<FaBookReader />}
          wrapperClassName="mt-1"
          id="bio"
          rows={USER_PROFILE_BIO_TEXTAREA_ROWS}
          value={form.data.bio || ''}
          onChange={(e) => form.setData('bio', e.target.value)}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_BIO}
        />
        <InputError className="mt-2" message={form.errors.bio} />
      </div>

      {/* Location */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="location">Location</InputLabel>
        <TextInput
          icon={<FaMapMarkerAlt />}
          wrapperClassName="mt-1"
          id="location"
          type="text"
          value={form.data.location || ''}
          onChange={(e) => form.setData('location', e.target.value)}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_LOCATION}
        />
        <InputError className="mt-2" message={form.errors.location} />
      </div>

      {/* Phone */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="phone">Phone</InputLabel>
        <TextInput
          icon={<FaPhone />}
          wrapperClassName="mt-1"
          id="phone"
          type="tel"
          value={form.data.phone || ''}
          onChange={(e) => form.setData('phone', e.target.value)}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_PHONE}
          allowedCharacters={USER_PHONE_REGEX}
        />
        <InputError className="mt-2" message={form.errors.phone} />
      </div>

      {/* Social Links */}
      <Collapsible title="Social Links" className="col-span-6 sm:col-span-4">
        {SOCIAL_MEDIA_LINK_PROPERTIES.map((link) => {
          const IconComponent = SOCIAL_MEDIA_LINK_ICONS[link as keyof typeof SOCIAL_MEDIA_LINK_ICONS];
          return (
            <div key={link as string} className="col-span-6 sm:col-span-4 mt-4">
              <InputLabel htmlFor={link as string}>{SOCIAL_MEDIA_LINK_NAMES[link as keyof typeof SOCIAL_MEDIA_LINK_NAMES]}</InputLabel>
              <TextInput
                icon={<IconComponent />}
                wrapperClassName="mt-1"
                id={link as string}
                type="url"
                placeholder="https://..."
                value={(form.data[link as keyof typeof form.data] as string) || ''}
                onChange={(e) => form.setData(link as keyof typeof form.data, e.target.value)}
                disabled={form.processing || userEmailNotVerified}
              />
              <InputError className="mt-2" message={form.errors[link as keyof typeof form.errors]} />
            </div>
          );
        })}
      </Collapsible>
    </FormSection>
  );
}
