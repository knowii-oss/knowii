import AppLayout from '@/Layouts/AppLayout';
import { Session, useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import Separator from '@/Components/Separator';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  return (
    <AppLayout title="Private profile" header={<h1 className="text-xl font-semibold leading-tight text-white">Your user profile</h1>}>
      {page.props.jetstream.canUpdateProfileInformation ? <UpdateProfileInformationForm user={page.props.auth.user!} /> : null}
      <Separator />

      {page.props.jetstream.canUpdatePassword ? <UpdatePasswordForm /> : null}
      <Separator />

      <LogoutOtherBrowserSessionsForm sessions={props.sessions} />
      <Separator />
    </AppLayout>
  );
}
