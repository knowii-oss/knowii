import AppLayout from '@/Layouts/AppLayout';
import type { AccountDeletionState, AccountEntitlements, ConnectedAccount, Session } from '@knowii/common';
import { useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import UpdateAvatarForm from '@/Pages/Profile/Partials/UpdateAvatarForm';
import OnlineStatusForm from '@/Pages/Profile/Partials/OnlineStatusForm';
import MemberListForm from '@/Pages/Profile/Partials/MemberListForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import ConnectedAccountsForm from '@/Pages/Profile/Partials/ConnectedAccountsForm';
import EntitlementsCard from '@/Pages/Profile/Partials/EntitlementsCard';
import type { BreadcrumbItem } from '@/Components/ui';
import { Divider } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import { FaUser } from 'react-icons/fa';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
  // Only when the instance has an OpenID Connect provider (#1385)
  connectedAccounts?: ConnectedAccount[];
  hasPassword?: boolean;
  // Whether the account has a password, with or without a provider: the profile form asks for it with a change of the
  // email address (#1706)
  accountHasPassword?: boolean;
  // Only when the instance reads entitlements and the member connected the provider account (#1386)
  entitlements?: AccountEntitlements;
  // Which proof the deletion of the account needs, and the result of a new sign-in (#1521)
  accountDeletion?: AccountDeletionState;
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  const breadcrumbItems: BreadcrumbItem[] = [{ label: 'Edit profile', icon: <FaUser /> }];

  return (
    <AppLayout contentWidth="form" browserPageTitle="Profile" breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      {page.props.auth.user && (
        <>
          <UpdateAvatarForm name={page.props.auth.user.name} avatarUrl={page.props.auth.user.avatar_url} />
          <Divider className="my-10" />
        </>
      )}

      {page.props.jetstream.canUpdateProfileInformation && page.props.auth.user && (
        <UpdateProfileInformationForm user={page.props.auth.user} hasPassword={props.accountHasPassword} />
      )}
      <Divider className="my-10" />

      {page.props.auth.user && (
        <>
          <OnlineStatusForm showOnlineStatus={page.props.auth.user.show_online_status !== false} />
          <Divider className="my-10" />
          <MemberListForm showInMemberList={page.props.auth.user.show_in_member_list !== false} />
          <Divider className="my-10" />
        </>
      )}

      {page.props.jetstream.canUpdatePassword && <UpdatePasswordForm />}
      <Divider className="my-10" />

      {page.props.jetstream.canManageTwoFactorAuthentication && (
        <>
          <TwoFactorAuthenticationForm requiresConfirmation={props.confirmsTwoFactorAuthentication} />
          <Divider className="my-10" />
        </>
      )}

      {props.connectedAccounts !== undefined && (
        <>
          <ConnectedAccountsForm accounts={props.connectedAccounts} hasPassword={props.hasPassword ?? true} />
          <Divider className="my-10" />
        </>
      )}

      {props.entitlements !== undefined && (
        <>
          <EntitlementsCard entitlements={props.entitlements} />
          <Divider className="my-10" />
        </>
      )}

      <LogoutOtherBrowserSessionsForm sessions={props.sessions} />
      <Divider className="my-10" />

      {page.props.jetstream.hasAccountDeletionFeatures && <DeleteUserForm deletion={props.accountDeletion} />}
    </AppLayout>
  );
}
