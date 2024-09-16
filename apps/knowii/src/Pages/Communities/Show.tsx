import AppLayout from '@/Layouts/AppLayout';
import CardGroup from '@/Components/CardGroup';
import { Community, CommunityPermissions, Permission, Role } from '@knowii/common';

interface Props {
  availablePermissions: string[];
  availableRoles: Role[];
  community: Community;
  defaultPermissions: Permission[];
  permissions: CommunityPermissions;
}

export default function Dashboard(props: Props) {
  return (
    <AppLayout title={props.community.name} pageTitle={props.community.name}>
      <h2 className="text-2xl font-bold mb-6 text-gray-800 border-b-2 border-primary-500 pb-2 block text-center sm:text-left">
        Resource collections
      </h2>
      <CardGroup className="mt-4">Coming soon...</CardGroup>
    </AppLayout>
  );
}
