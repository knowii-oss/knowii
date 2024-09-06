import { User, USER_PROFILE_INFORMATION_UPDATE_URL, useTypedPage } from '@knowii/common';
import { Link, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { FormEventHandler, useState } from 'react';
import ActionMessage from '@/Components/ActionMessage';
import { Button } from 'primereact/button';
import FormSection from '@/Components/FormSection';
import classNames from 'classnames';
import InputLabel from '@/Components/InputLabel';
import { FaAt, FaUser } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';

interface Props {
  user: User;
}

interface UpdateProfileInformationFormData {
  name: string;
  email: string;
  photo: File | null;
}

export default function UpdateProfileInformationForm(props: Props) {
  const route = useRoute();
  const page = useTypedPage();

  const [verificationLinkSent, setVerificationLinkSent] = useState(false);

  const form = useForm<UpdateProfileInformationFormData>({
    name: props.user.name,
    email: props.user.email,
    photo: null as File | null,
  });

  const updateProfileInformation: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route(USER_PROFILE_INFORMATION_UPDATE_URL), {
      errorBag: 'updateProfileInformation',
      preserveScroll: true,
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
          <ActionMessage on={form.recentlySuccessful} className="mr-3">
            Saved.
          </ActionMessage>

          <Button severity="primary" className={classNames({ 'opacity-25': form.processing })} disabled={form.processing}>
            Save
          </Button>
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
          />
        </div>
        <InputError className="mt-2" message={form.errors.email} />

        {page.props.jetstream.hasEmailVerification && props.user.email_verified_at === null ? (
          <div>
            <p className="text-sm mt-2">
              Your email address is unverified.&nbsp;
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
    </FormSection>
  );
}
