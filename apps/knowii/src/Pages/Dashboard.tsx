import AppLayout from '@/Layouts/AppLayout';
import CommunityBox from '@/Components/CommunityBox';
import { Link } from '@inertiajs/react';
import { Community } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';

export default function Dashboard() {
  const communityToCreate: Community = {
    cuid: '',
    name: 'New one',
    description: '',
    personal_community: false,
    created_at: '',
    updated_at: '',
  };

  const createNewCommunity = () => {
    alert('lol');
  };

  return (
    <AppLayout title="Dashboard" pageTitle="Dashboard">
      <h2 className="text-2xl font-bold mb-6 text-gray-800 border-b-2 border-primary-500 pb-2 block text-center sm:text-left">
        My Communities
      </h2>
      {/* TODO add link to open the given community page */}
      <CardGroup className="mt-4">
        {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20].map((item) => (
          <Link href={''}>
            {/* TODO add link to open the given community page. The link should use the slug */}
            <CommunityBox
              community={{
                name: `Community ${item}`,
                description: `This is the description for community ${item}.`,
                cuid: '',
                created_at: '',
                updated_at: '',
                personal_community: false,
              }}
              creationMode={false}
            />
          </Link>
        ))}
        <CommunityBox community={communityToCreate} creationMode={true} clickHandler={createNewCommunity} />
      </CardGroup>
    </AppLayout>
  );
}
