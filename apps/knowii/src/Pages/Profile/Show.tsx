import AppLayout from '@/Layouts/AppLayout';
import { Session, useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import Separator from '@/Components/Separator';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  return (
    <AppLayout title="Private profile" header={<h1 className="text-xl font-semibold leading-tight text-white">Your user profile</h1>}>
      {page.props.jetstream.canUpdateProfileInformation && page.props.auth.user && (
        <UpdateProfileInformationForm user={page.props.auth.user} />
      )}
      <Separator />

      {page.props.jetstream.canUpdatePassword && <UpdatePasswordForm />}
      <Separator />

      <LogoutOtherBrowserSessionsForm sessions={props.sessions} />
      <Separator />

      {page.props.jetstream.hasAccountDeletionFeatures && <DeleteUserForm />}
    </AppLayout>
  );
}
