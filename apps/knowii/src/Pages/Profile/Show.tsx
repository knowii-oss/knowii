import AppLayout from '@/Layouts/AppLayout';
import type { Session } from '@knowii/common';
import { useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import UpdateAvatarForm from '@/Pages/Profile/Partials/UpdateAvatarForm';
import Separator from '@/Components/Separator';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import type { BreadcrumbItem } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import { FaUser } from 'react-icons/fa';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  const breadcrumbItems: BreadcrumbItem[] = [{ label: 'Edit profile', icon: <FaUser /> }];

  return (
    <AppLayout browserPageTitle="Profile" breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      {page.props.auth.user && (
        <>
          <UpdateAvatarForm name={page.props.auth.user.name} avatarUrl={page.props.auth.user.avatar_url} />
          <Separator />
        </>
      )}

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
