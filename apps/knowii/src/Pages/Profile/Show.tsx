import AppLayout from '@/Layouts/AppLayout';
import { Session, useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import Separator from '@/Components/Separator';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import { MenuItem } from 'primereact/menuitem';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  const breadcrumbItems: MenuItem[] = [{ label: 'Your user profile' }];

  return (
    <AppLayout title="Private profile" breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome}>
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
