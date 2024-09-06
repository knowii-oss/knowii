import { USER_PROFILE_PASSWORD_UPDATE_URL } from '@knowii/common';
import { useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { FormEventHandler, useRef } from 'react';
import { Button } from 'primereact/button';
import FormSection from '@/Components/FormSection';
import classNames from 'classnames';
import InputLabel from '@/Components/InputLabel';
import { FaLock } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { Toast } from 'primereact/toast';

interface UpdatePasswordFormData {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export default function UpdatePasswordForm() {
  const route = useRoute();

  const form = useForm<UpdatePasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const passwordRef = useRef<HTMLInputElement | null>(null);
  const currentPasswordRef = useRef<HTMLInputElement | null>(null);
  const toastRef = useRef<Toast | null>(null);

  const updatePassword: FormEventHandler = (e) => {
    e.preventDefault();

    form.put(route(USER_PROFILE_PASSWORD_UPDATE_URL), {
      errorBag: 'updatePassword',
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        toastRef.current?.show({ severity: 'success', summary: 'Success', detail: 'Password updated successfully.' });
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
        <>
          <Toast position="bottom-center" ref={toastRef} />

          <Button className={classNames({ 'opacity-25': form.processing })} disabled={form.processing}>
            Save
          </Button>
        </>
      )}
    >
      {/* Current password */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="current_password">Current Password</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaLock />
          </span>
          <InputText
            id="current_password"
            type="password"
            className="mt-1 block w-full"
            ref={currentPasswordRef}
            value={form.data.current_password}
            onChange={(e) => form.setData('current_password', e.target.value)}
            required
            autoComplete="current-password"
          />
        </div>
        <InputError className="mt-2" message={form.errors.current_password} />
      </div>

      {/* New password */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="password">New Password</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaLock />
          </span>
          <InputText
            id="password"
            type="password"
            className="mt-1 block w-full"
            ref={passwordRef}
            value={form.data.password}
            onChange={(e) => form.setData('password', e.target.value)}
            required
            autoComplete="new-password"
          />
        </div>
        <InputError className="mt-2" message={form.errors.password} />
      </div>

      {/* New password confirmation */}
      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="password_confirmation">Confirm Password</InputLabel>
        <div className="p-inputgroup mt-1">
          <span className="p-inputgroup-addon mt-1">
            <FaLock />
          </span>
          <InputText
            id="password_confirmation"
            type="password"
            className="mt-1 block w-full"
            value={form.data.password_confirmation}
            onChange={(e) => form.setData('password_confirmation', e.target.value)}
            required
            autoComplete="new-password"
          />
        </div>
        <InputError className="mt-2" message={form.errors.password_confirmation} />
      </div>
    </FormSection>
  );
}
