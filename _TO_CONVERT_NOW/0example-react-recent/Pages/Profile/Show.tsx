import React from 'react';
import { usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';
import SectionBorder from '@/Components/SectionBorder';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';

const Profile = ({ confirmsTwoFactorAuthentication, sessions }: any) => {
  const { props } = usePage();
  const { auth, jetstream }: any = props;

  return (
    <AppLayout title="Profile" header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Profile</h2>}>
      <div>
        <div className="py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
          {jetstream.canUpdateProfileInformation && (
            <>
              <UpdateProfileInformationForm user={auth.user} jetstream={jetstream} />
              <SectionBorder />
            </>
          )}

          {jetstream.canUpdatePassword && (
            <>
              <div className="mt-10 sm:mt-0">
                <UpdatePasswordForm />
              </div>
              <SectionBorder />
            </>
          )}

          {jetstream.canManageTwoFactorAuthentication && (
            <>
              <div className="mt-10 sm:mt-0">
                <TwoFactorAuthenticationForm requiresConfirmation={confirmsTwoFactorAuthentication} />
              </div>
              <SectionBorder />
            </>
          )}

          <div className="mt-10 sm:mt-0">
            <LogoutOtherBrowserSessionsForm sessions={sessions} />
          </div>

          {jetstream.hasAccountDeletionFeatures && (
            <>
              <SectionBorder />
              <DeleteUserForm className="mt-10 sm:mt-0" />
            </>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default Profile;
