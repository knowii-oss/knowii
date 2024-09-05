import React, { useState, useRef } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import ActionMessage from '@/Components/ActionMessage';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

const UpdateProfileInformationForm = ({ user, jetstream }: any) => {
  const [verificationLinkSent, setVerificationLinkSent] = useState(false);
  const [photoPreview, setPhotoPreview] = useState(null);
  const photoInput: any = useRef(null);

  const form = useForm({
    _method: 'PUT',
    name: user.name,
    email: user.email,
    photo: null,
  });

  const updateProfileInformation = (e: any) => {
    e.preventDefault();

    form.post(route('user-profile-information.update'), {
      errorBag: 'updateProfileInformation',
      preserveScroll: true,
      onSuccess: () => clearPhotoFileInput(),
    });
  };

  const sendEmailVerification = (e: any) => {
    e.preventDefault();
    setVerificationLinkSent(true);
  };

  const selectNewPhoto = () => {
    photoInput.current.click();
  };

  const updatePhotoPreview = () => {
    const photo = photoInput.current.files[0];

    if (photo) {
      form.setData('photo', photo);
    }

    if (!photo) return;

    const reader = new FileReader();

    reader.onload = (e: any) => {
      setPhotoPreview(e.target.result);
    };

    reader.readAsDataURL(photo);
  };

  const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
      preserveScroll: true,
      onSuccess: () => {
        setPhotoPreview(null);
        clearPhotoFileInput();
      },
    });
  };

  const clearPhotoFileInput = () => {
    if (photoInput.current?.value) {
      photoInput.current.value = null;
    }
  };

  return (
    <FormSection
      onSubmit={updateProfileInformation}
      title="Profile Information"
      description="Update your account's profile information and email address."
    >
      {jetstream.managesProfilePhotos && (
        <div className="col-span-6 sm:col-span-4">
          <input id="photo" ref={photoInput} type="file" className="hidden" onChange={updatePhotoPreview} />

          <InputLabel htmlFor="photo" value="Photo" />

          {!photoPreview ? (
            <div className="mt-2">
              <img src={user.profile_photo_url} alt={user.name} className="object-cover w-20 h-20 rounded-full" />
            </div>
          ) : (
            <div className="mt-2">
              <span
                className="block w-20 h-20 bg-center bg-no-repeat bg-cover rounded-full"
                style={{
                  backgroundImage: `url('${photoPreview}')`,
                }}
              />
            </div>
          )}

          <SecondaryButton className="mt-2 me-2" type="button" onClick={selectNewPhoto}>
            Select A New Photo
          </SecondaryButton>

          {user.profile_photo_path && (
            <SecondaryButton type="button" className="mt-2" onClick={deletePhoto}>
              Remove Photo
            </SecondaryButton>
          )}

          <InputError message={form.errors.photo} className="mt-2" />
        </div>
      )}

      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="name" value="Name" />
        <TextInput
          id="name"
          value={form.data.name}
          onChange={(e) => form.setData('name', e.target.value)}
          type="text"
          className="block w-full mt-1"
          required
          autoComplete="name"
        />
        <InputError message={form.errors.name} className="mt-2" />
      </div>

      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="email" value="Email" />
        <TextInput
          id="email"
          value={form.data.email}
          onChange={(e) => form.setData('email', e.target.value)}
          type="email"
          className="block w-full mt-1"
          required
          autoComplete="username"
        />
        <InputError message={form.errors.email} className="mt-2" />

        {user.hasEmailVerification && user.email_verified_at === null && (
          <div>
            <p className="mt-2 text-sm dark:text-white">
              Your email address is unverified.
              <Link
                href={route('verification.send')}
                method="post"
                as="button"
                className="text-sm text-gray-600 underline rounded-md dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                onClick={sendEmailVerification}
              >
                Click here to re-send the verification email.
              </Link>
            </p>

            {verificationLinkSent && (
              <div className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                A new verification link has been sent to your email address.
              </div>
            )}
          </div>
        )}
      </div>

      <div className="flex items-center mt-4">
        <ActionMessage on={form.recentlySuccessful} className="me-3">
          Saved.
        </ActionMessage>

        <PrimaryButton className={form.processing ? 'opacity-25' : ''} disabled={form.processing}>
          Save
        </PrimaryButton>
      </div>
    </FormSection>
  );
};

export default UpdateProfileInformationForm;
