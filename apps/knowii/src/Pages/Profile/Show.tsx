import AppLayout from '@/Layouts/AppLayout';
import { Session, useTypedPage } from '@knowii/common';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm';
import { Divider } from 'primereact/divider';

interface Props {
  confirmsTwoFactorAuthentication: boolean;
  sessions: Session[];
}

export default function UserProfile(props: Props) {
  const page = useTypedPage();

  return (
    <AppLayout title="Private profile" header={<h1 className="text-xl font-semibold leading-tight text-white">Your user profile</h1>}>
      {page.props.jetstream.canUpdateProfileInformation ? (
        <div className="">
          <UpdateProfileInformationForm user={page.props.auth.user!} />
        </div>
      ) : null}
      <Divider />
    </AppLayout>
  );
}
