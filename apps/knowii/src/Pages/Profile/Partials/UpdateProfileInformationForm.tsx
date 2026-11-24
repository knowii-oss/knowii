import type { CurrentUser, ProfileFieldVisibility, ProfileVisibilityField, UserProfile } from '@knowii/common';
import {
  resolveProfileFieldVisibility,
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
  MAX_LENGTH_USER_HEADLINE,
  MAX_LENGTH_USER_LOCATION,
  MAX_LENGTH_USER_BIO,
  MAX_LENGTH_USER_PHONE,
  USER_PHONE_REGEX,
  USER_PROFILE_BIO_TEXTAREA_ROWS,
  EMAIL_VERIFICATION_URL,
  EMAIL_CHANGE_RESEND_URL,
  EMAIL_CHANGE_CANCEL_URL,
} from '@knowii/common';
import { Link, router, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useEffect, useRef, useState } from 'react';
import FormSection from '@/Components/FormSection';
import { FaAt, FaBookReader, FaIdBadge, FaLock, FaMapMarkerAlt, FaPassport, FaPhone, FaUser } from 'react-icons/fa';
import { Button, Checkbox, Collapsible, InputError, InputLabel, PasswordInput, Textarea, TextInput } from '@/Components/ui';

interface Props {
  user: CurrentUser;
  // Whether the account has a password (#1706): the form asks for it with a change of the email address. Absent: yes
  hasPassword?: boolean | undefined;
}

type UpdateProfileInformationFormData = Omit<
  CurrentUser,
  | 'created_at'
  | 'updated_at'
  | 'email_verified_at'
  | 'cuid'
  | 'two_factor_enabled'
  | 'two_factor_confirmed_at'
  | 'pending_email'
  | 'pending_email_requested_at'
> &
  Omit<UserProfile, 'username' | 'field_visibility'> & { field_visibility: ProfileFieldVisibility; current_password: string };

/**
 * "Visible to members" under a profile field (#1076, ADR 0020): the member chooses what the other members see
 */
function VisibleToMembers(props: {
  field: ProfileVisibilityField;
  // Name of the field, for screen readers: every switch has the same visible text
  fieldLabel: string;
  checked: boolean;
  disabled: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm text-muted">
      <Checkbox
        name={`field_visibility.${props.field}`}
        checked={props.checked}
        disabled={props.disabled}
        onChange={(event) => props.onChange(event.target.checked)}
      />
      <span>
        Visible to the members of my communities<span className="sr-only">, {props.fieldLabel}</span>
      </span>
    </label>
  );
}

export default function UpdateProfileInformationForm(props: Props) {
  const route = useRoute();
  const page = useTypedPage();

  const userEmailNotVerified = page.props.jetstream.hasEmailVerification && props.user.email_verified_at === null;

  const { showToast } = useAppData();

  const [verificationLinkSent, setVerificationLinkSent] = useState(false);

  // Some fields of the user profile are 1:1 copies of the user account, so they need to be ignored here
  const {
    name: nameToIgnore,
    username: usernameToIgnore,
    field_visibility: storedFieldVisibility,
    ...userProfileFieldsToInclude
  } = page.props.userProfile;

  const form = useForm<UpdateProfileInformationFormData>({
    ...props.user,
    ...userProfileFieldsToInclude,
    field_visibility: resolveProfileFieldVisibility(storedFieldVisibility),
    current_password: '',
  });

  // A change of the email address applies after the new address is confirmed (#1706, ADR 0058). The same address in
  // another case is no change. The current password confirms a change when the account has one
  const storedEmail = props.user.email;
  const pendingEmail = props.user.pending_email ?? null;
  const pendingRequestedAt = props.user.pending_email_requested_at ?? null;
  const emailChanged = form.data.email.trim().toLowerCase() !== storedEmail.toLowerCase();
  const asksForPassword = emailChanged && props.hasPassword !== false;
  const emailRef = useRef<HTMLInputElement>(null);
  const [emailChangeAction, setEmailChangeAction] = useState<'resend' | 'cancel' | null>(null);

  // After a save, the field holds the address of the account again: a new address waits in the line under the field
  useEffect(() => {
    form.setData('email', storedEmail);
    form.setData('current_password', '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [storedEmail, pendingEmail, String(pendingRequestedAt)]);

  const resendEmailChange = () => {
    setEmailChangeAction('resend');
    router.post(route(EMAIL_CHANGE_RESEND_URL), {}, { preserveScroll: true, onFinish: () => setEmailChangeAction(null) });
  };

  const cancelEmailChange = () => {
    setEmailChangeAction('cancel');
    router.delete(route(EMAIL_CHANGE_CANCEL_URL), {
      preserveScroll: true,
      onFinish: () => setEmailChangeAction(null),
      // The line and its buttons go: the focus goes to the email field
      onSuccess: () => emailRef.current?.focus(),
    });
  };

  const visibilityToggle = (field: ProfileVisibilityField, fieldLabel: string) => (
    <VisibleToMembers
      field={field}
      fieldLabel={fieldLabel}
      checked={form.data.field_visibility[field]}
      disabled={form.processing || userEmailNotVerified}
      onChange={(checked) => {
        form.setData('field_visibility', { ...form.data.field_visibility, [field]: checked });
        form.clearErrors(`field_visibility.${field}`);
      }}
    />
  );

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
    const changesEmail = emailChanged;

    form.put(route(USER_PROFILE_INFORMATION_UPDATE_URL), {
      // WARNING: aligned with the error bag of UpdateUserProfileInformation.php. Without it, the errors of the server
      // come under the name of the bag, and no field shows its error
      errorBag: 'updateProfileInformation',
      preserveScroll: true,
      onSuccess: () => {
        // A new address waits for its link (ADR 0058): the same words for every new address
        showToast({
          severity: 'success',
          summary: 'Success',
          detail: changesEmail ? 'We sent a link to the new address.' : 'Profile updated successfully.',
        });
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
            Your name, username and picture show next to what you write. In a public space, visitors see them too. Your email address is
            never shown to other members. Headline, bio, location and phone: you choose below.
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
              // The stored values come back, so no error of the changed values is true any more
              form.resetAndClearErrors();
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
          onChange={(e) => {
            // A change of a field removes the error of that field, here and below: the member corrects the text and saves
            // again. Without this, an error of the server keeps "Save" disabled until the page loads again
            form.setData('name', e.target.value);
            form.clearErrors('name');
          }}
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
          ref={emailRef}
          type="email"
          value={form.data.email}
          onChange={(e) => {
            form.setData('email', e.target.value);
            form.clearErrors('email');
          }}
          autoComplete="username"
          required
          // An address that was never verified can be corrected (#1706): the link goes to the new address, as a change
          disabled={form.processing}
        />
        <InputError className="mt-2" message={form.errors.email} />

        {pendingEmail !== null && (
          <div className="mt-2 text-sm">
            <p>
              Confirm your new email address. We sent a link to <strong className="break-words">{pendingEmail}</strong>.
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
              <Button variant="secondary" size="sm" loading={emailChangeAction === 'resend'} onClick={resendEmailChange}>
                Send the link again
              </Button>
              <Button variant="secondary" size="sm" loading={emailChangeAction === 'cancel'} onClick={cancelEmailChange}>
                Cancel the change
              </Button>
            </div>
          </div>
        )}

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

      {/* The current password confirms a change of the email address (#1706) */}
      {asksForPassword && (
        <div className="col-span-6 sm:col-span-4">
          <InputLabel htmlFor="email_current_password">Current password</InputLabel>
          <PasswordInput
            icon={<FaLock />}
            wrapperClassName="mt-1"
            id="email_current_password"
            name="current_password"
            value={form.data.current_password}
            onChange={(e) => {
              form.setData('current_password', e.target.value);
              form.clearErrors('current_password');
            }}
            required
            autoComplete="current-password"
            disabled={form.processing}
            invalid={Boolean(form.errors.current_password)}
            aria-describedby={form.errors.current_password ? 'email_current_password-error' : undefined}
          />
          <InputError id="email_current_password-error" className="mt-2" message={form.errors.current_password} />
        </div>
      )}

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
            // The error of the last save goes first: the checks below give the error of the new username, if it has one
            form.clearErrors('username');
            validateUsernameLocally(e.target.value);
          }}
          autoComplete="username"
          disabled={form.processing || userEmailNotVerified}
          required
        />
        <InputError className="mt-2" message={form.errors.username} />
      </div>

      {/* Headline (#1688): one line of plain text, shown under the name */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="headline">Headline</InputLabel>
        <TextInput
          icon={<FaIdBadge />}
          wrapperClassName="mt-1"
          id="headline"
          type="text"
          value={form.data.headline || ''}
          onChange={(e) => {
            form.setData('headline', e.target.value);
            form.clearErrors('headline');
          }}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_HEADLINE}
          invalid={Boolean(form.errors.headline)}
          aria-describedby={form.errors.headline ? 'headline-hint headline-error' : 'headline-hint'}
        />
        <p id="headline-hint" className="mt-1 text-xs text-muted">
          One line about you. It shows under your name.
        </p>
        {visibilityToggle('headline', 'Headline')}
        <InputError id="headline-error" className="mt-2" message={form.errors.headline} />
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
          onChange={(e) => {
            form.setData('bio', e.target.value);
            form.clearErrors('bio');
          }}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_BIO}
        />
        {visibilityToggle('bio', 'Bio')}
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
          onChange={(e) => {
            form.setData('location', e.target.value);
            form.clearErrors('location');
          }}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_LOCATION}
        />
        {visibilityToggle('location', 'Location')}
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
          onChange={(e) => {
            form.setData('phone', e.target.value);
            form.clearErrors('phone');
          }}
          disabled={form.processing || userEmailNotVerified}
          maxLength={MAX_LENGTH_USER_PHONE}
          allowedCharacters={USER_PHONE_REGEX}
        />
        {visibilityToggle('phone', 'Phone')}
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
                onChange={(e) => {
                  form.setData(link as keyof typeof form.data, e.target.value);
                  form.clearErrors(link as keyof typeof form.errors);
                }}
                disabled={form.processing || userEmailNotVerified}
              />
              {visibilityToggle(link, SOCIAL_MEDIA_LINK_NAMES[link as keyof typeof SOCIAL_MEDIA_LINK_NAMES])}
              <InputError className="mt-2" message={form.errors[link as keyof typeof form.errors]} />
            </div>
          );
        })}
      </Collapsible>
    </FormSection>
  );
}
