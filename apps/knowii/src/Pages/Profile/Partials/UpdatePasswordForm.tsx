import { useAppData, USER_PROFILE_PASSWORD_UPDATE_URL, useTypedPage } from '@knowii/common';
import { useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import type { FormEventHandler } from 'react';
import { useRef } from 'react';
import FormSection from '@/Components/FormSection';
import InputLabel from '@/Components/InputLabel';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import { Button, PasswordInput } from '@/Components/ui';

interface UpdatePasswordFormData {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export default function UpdatePasswordForm() {
  const page = useTypedPage();
  const route = useRoute();

  const { showToast } = useAppData();

  const form = useForm<UpdatePasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);
  const currentPasswordRef = useRef<HTMLInputElement | null>(null);

  const updatePassword: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route(USER_PROFILE_PASSWORD_UPDATE_URL), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        showToast({ severity: 'success', summary: 'Success', detail: 'Password updated successfully.' });
      },
      onError: () => {
        if (form.errors.password) {
          form.reset('password', 'password_confirmation');
          passwordRef.current?.focus();
        }

        if (form.errors.current_password) {
          form.reset('current_password');
          currentPasswordRef.current?.focus();
        }
      },
    });
  };

  return (
    <FormSection
      onSubmit={updatePassword}
      title={'Update Password'}
      description={`Ensure your account is using a long, random password to stay secure.`}
      renderActions={() => (
        <Button type="submit" loading={form.processing}>
          Save
        </Button>
      )}
    >
      {/* The account of the password, for password managers: they save and suggest passwords per username (#813) */}
      <input type="text" name="username" autoComplete="username" value={page.props.auth.user?.email ?? ''} readOnly hidden />

      {/* Current password */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="current_password">Current Password</InputLabel>
        <PasswordInput
          icon={<FaLock />}
          wrapperClassName="mt-1"
          id="current_password"
          name="current_password"
          ref={currentPasswordRef}
          value={form.data.current_password}
          onChange={(e) => form.setData('current_password', e.target.value)}
          required
          autoComplete="current-password"
          disabled={form.processing}
        />
        <InputError className="mt-2" message={form.errors.current_password} />
      </div>

      {/* New password */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="password">New Password</InputLabel>
        <PasswordInput
          icon={<FaLock />}
          wrapperClassName="mt-1"
          id="password"
          name="password"
          showStrength
          ref={passwordRef}
          value={form.data.password}
          onChange={(e) => form.setData('password', e.target.value)}
          required
          autoComplete="new-password"
          disabled={form.processing}
        />
        <InputError className="mt-2" message={form.errors.password} />
      </div>

      {/* New password confirmation */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="password_confirmation">Confirm Password</InputLabel>
        <PasswordInput
          icon={<FaLock />}
          wrapperClassName="mt-1"
          id="password_confirmation"
          name="password_confirmation"
          value={form.data.password_confirmation}
          onChange={(e) => form.setData('password_confirmation', e.target.value)}
          required
          autoComplete="new-password"
          disabled={form.processing}
        />
        <InputError className="mt-2" message={form.errors.password_confirmation} />
      </div>
    </FormSection>
  );
}
