import React, { useState, useRef, type FormEventHandler } from 'react';
import axios from 'axios';
import { useForm } from '@inertiajs/react';
import DialogModal from './DialogModal';
import InputError from './InputError';
import PrimaryButton from './PrimaryButton';
import SecondaryButton from './SecondaryButton';
import TextInput from './TextInput';

const ConfirmPasswordModal = ({
  title = 'Confirm Password',
  content = 'For your security, please confirm your password to continue.',
  button = 'Confirm',
  onConfirmed,
  children,
}: any) => {
  const [confirmingPassword, setConfirmingPassword] = useState(false);
  const passwordInput: any = useRef(null);

  const form = useForm({
    password: '',
    error: '',
    processing: false,
  });

  const startConfirmingPassword: FormEventHandler = (e) => {
    axios.get(route('password.confirmation')).then((response) => {
      if (response.data.confirmed) {
        onConfirmed(e);
      } else {
        setConfirmingPassword(true);
        setTimeout(() => passwordInput.current.focus(), 250);
      }
    });
  };

  const confirmPassword: FormEventHandler = (e) => {
    e.preventDefault();
    form.setData('processing', true);

    axios
      .post(route('password.confirm'), {
        password: form.data.password,
      })
      .then(() => {
        form.setData('processing', false);
        closeModal();
        onConfirmed(e);
      })
      .catch((error) => {
        form.setData('processing', false);
        form.setData('error', error.response.data.errors.password[0]);
        passwordInput.current.focus();
      });
  };

  const closeModal = () => {
    setConfirmingPassword(false);
    form.setData('password', '');
    form.setData('error', '');
  };

  return (
    <span>
      <span onClick={startConfirmingPassword}>{children}</span>

      <DialogModal
        show={confirmingPassword}
        onClose={closeModal}
        title={<h2>{title}</h2>}
        content={
          <>
            <p>{content}</p>
            <div className="mt-4">
              <TextInput
                ref={passwordInput}
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                type="password"
                className="block w-3/4 mt-1"
                placeholder="Password"
                autoComplete="current-password"
                onKeyUp={(e) => {
                  if (e.key === 'Enter') confirmPassword(e);
                }}
              />
              <InputError message={form.data.error} className="mt-2" />
            </div>
            <div className="mt-4">
              <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
              <PrimaryButton
                className={`ms-3 ${form.data.processing ? 'opacity-25' : ''}`}
                disabled={form.data.processing}
                onClick={confirmPassword}
              >
                {button}
              </PrimaryButton>
            </div>
          </>
        }
      ></DialogModal>
    </span>
  );
};

export default ConfirmPasswordModal;
