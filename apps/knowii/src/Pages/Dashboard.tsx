import AppLayout from '@/Layouts/AppLayout';
import { useCallback, useState } from 'react';
import CommunityBox from '@/Components/Communities/CommunityBox';
import { Community, COMMUNITY_URL, communityVisibilityOptions, Identifiable, useSocket } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';
import { useImmer } from 'use-immer';
import { useRoute } from 'ziggy-js';
import CreateCommunityDialog from '@/Components/Communities/CreateCommunityDialog';
import { MenuItem } from 'primereact/menuitem';
import { FaHome } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import { Dropdown } from 'primereact/dropdown';
import { Button } from 'primereact/button';
import CommunityIcon from '@/Components/Communities/CommunityIcon';

interface Props {
  communities: Community[];
}

export default function DashboardPage(props: Props) {
  const route = useRoute();

  const handleCommunityCreated = (newCommunity: Community) => {
    setCommunities((draft) => {
      if (!draft.find((community) => community.cuid === newCommunity.cuid)) {
        draft.push(newCommunity);
        draft.sort((a, b) => a.name.localeCompare(b.name));
      }
    });
  };

  const handleCommunityDeleted = (deletedCommunity: Identifiable) => {
    setCommunities((draft) => {
      const index = draft.findIndex((community) => community.cuid === deletedCommunity.cuid);
      if (index !== -1) {
        draft.splice(index, 1);
      }
    });
  };

  useSocket({
    channel: {
      type: 'communities',
    },
    event: 'community.created',
    callback: (_event, payload) => {
      handleCommunityCreated(payload);
    },
  });

  useSocket({
    channel: {
      type: 'communities',
    },
    event: 'community.deleted',
    callback: (_event, payload) => {
      handleCommunityDeleted(payload);
    },
  });

  const breadcrumbItems: MenuItem[] = [
    {
      label: 'Home',
      template: (item) => (
        <span className="flex items-center gap-2">
          <FaHome />
          {item.label}
        </span>
      ),
    },
  ];

  const [creatingCommunity, setCreatingCommunity] = useState(false);
  const [communities, setCommunities] = useImmer<Community[]>(props.communities);
  const [searchTerm, setSearchTerm] = useState('');
  const [visibilityFilter, setVisibilityFilter] = useState('');

  const openCreateCommunityModal = () => {
    setCreatingCommunity(true);
  };

  const closeCreateCommunityModal = () => {
    setCreatingCommunity(false);
  };

  const filteredCommunities = useCallback(() => {
    return communities.filter((community) => {
      const matchesSearch =
        community.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        community.description.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesVisibility = visibilityFilter ? community.visibility === visibilityFilter : true;
      return matchesSearch && matchesVisibility;
    });
  }, [communities, searchTerm, visibilityFilter]);

  const clearFilters = () => {
    setSearchTerm('');
    setVisibilityFilter('');
  };

  return (
    <>
      <AppLayout browserPageTitle="Dashboard" breadcrumbItems={breadcrumbItems}>
        <h2 className="text-2xl font-bold mb-6 text-gray-800 border-b-2 border-primary-500 pb-2 block text-center sm:text-left">
          My Communities
        </h2>
        <div className="flex flex-col sm:flex-row sm:justify-center md:justify-start gap-4 mb-4">
          <InputText
            placeholder="Search communities..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="p-inputtext-sm md:w-[34rem] lg:w-[48rem]"
          />
          <Dropdown
            options={communityVisibilityOptions}
            optionLabel="name"
            optionValue="visibility"
            itemTemplate={(option) => (
              <span className="text-sm flex items-center gap-2">
                <CommunityIcon visibility={option.visibility} />
                {option.name}
              </span>
            )}
            value={visibilityFilter}
            onChange={(e) => setVisibilityFilter(e.value)}
            placeholder="Filter by visibility"
            className="p-inputtext-sm"
          />
          <Button severity="secondary" label="Clear Filters" onClick={clearFilters} className="p-button-sm" />
        </div>
        <CardGroup className="mt-4">
          {filteredCommunities().map((item) => (
            <li className="w-full sm:w-auto" key={item.cuid}>
              <CommunityBox
                community={item}
                creationMode={false}
                link={route(COMMUNITY_URL, {
                  communitySlug: item.slug,
                })}
              />
            </li>
          ))}
          <li className="w-full sm:w-auto">
            <CommunityBox creationMode={true} clickHandler={openCreateCommunityModal} />
          </li>
        </CardGroup>
      </AppLayout>
      <CreateCommunityDialog visible={creatingCommunity} onHide={closeCreateCommunityModal} onCommunityCreated={handleCommunityCreated} />
    </>
  );
}
