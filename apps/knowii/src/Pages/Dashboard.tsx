import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';
import CommunityBox from '@/Components/Communities/CommunityBox';
import { Community, COMMUNITY_URL, useTypedPage } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';
import { useImmer } from 'use-immer';
import { useRoute } from 'ziggy-js';
import CreateCommunityDialog from '@/Components/Communities/CreateCommunityDialog';

export default function DashboardPage() {
  const page = useTypedPage();
  const route = useRoute();

  const [creatingCommunity, setCreatingCommunity] = useState(false);
  const [communities, updateCommunities] = useImmer<Community[]>(page.props.communities);

  const openCreateCommunityModal = () => {
    setCreatingCommunity(true);
  };

  const closeCreateCommunityModal = () => {
    setCreatingCommunity(false);
  };

  const handleCommunityCreated = (newCommunity: Community) => {
    updateCommunities((draft) => {
      draft.push(newCommunity);
    });
  };

  return (
    <>
      <AppLayout title="Dashboard" pageTitle="Dashboard">
        <h2 className="text-2xl font-bold mb-6 text-gray-800 border-b-2 border-primary-500 pb-2 block text-center sm:text-left">
          My Communities
        </h2>
        <CardGroup className="mt-4">
          {communities.map((item) => (
            <CommunityBox
              key={item.cuid}
              community={item}
              creationMode={false}
              link={route(COMMUNITY_URL, {
                communitySlug: item.slug,
              })}
            />
          ))}
          <CommunityBox creationMode={true} clickHandler={openCreateCommunityModal} />
        </CardGroup>
      </AppLayout>
      <CreateCommunityDialog visible={creatingCommunity} onHide={closeCreateCommunityModal} onCommunityCreated={handleCommunityCreated} />
    </>
  );
}
