import AppLayout from '@/Layouts/AppLayout';
import { Community, CommunityPermissions, CommunityResourceCollection } from '@knowii/common';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollection: CommunityResourceCollection;
}

export default function ResourceCollectionPage(props: Props) {
  console.log(props);
  return (
    <AppLayout title={props.community.name} pageTitle={props.resourceCollection.name}>
      Coming soon...
    </AppLayout>
  );
}
