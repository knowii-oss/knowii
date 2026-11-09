import AppLayout from '@/Layouts/AppLayout';
import { useCallback, useState } from 'react';
import CommunityBox from '@/Components/Communities/CommunityBox';
import type { Community, CommunityVisibility, Identifiable } from '@knowii/common';
import { COMMUNITY_URL, communityVisibilityOptions, useSocket } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';
import { useImmer } from 'use-immer';
import { useRoute } from 'ziggy-js';
import CreateCommunityDialog from '@/Components/Communities/CreateCommunityDialog';
import { FaHome, FaSearch } from 'react-icons/fa';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, Select, TextInput } from '@/Components/ui';

const visibilityFilterOptions = communityVisibilityOptions.map((option) => ({
  value: option.visibility,
  label: option.name,
  icon: <CommunityIcon visibility={option.visibility} />,
}));

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

  const breadcrumbItems: BreadcrumbItem[] = [{ label: 'Home', icon: <FaHome /> }];

  const [creatingCommunity, setCreatingCommunity] = useState(false);
  const [communities, setCommunities] = useImmer<Community[]>(props.communities);
  const [searchTerm, setSearchTerm] = useState('');
  const [visibilityFilter, setVisibilityFilter] = useState<CommunityVisibility | null>(null);

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
    setVisibilityFilter(null);
  };

  return (
    <>
      <AppLayout browserPageTitle="Dashboard" breadcrumbItems={breadcrumbItems}>
        <h2 className="mb-6 text-center text-2xl font-extrabold sm:text-left">My Communities</h2>
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-center md:justify-start">
          <TextInput
            type="search"
            size="sm"
            icon={<FaSearch />}
            aria-label="Search communities"
            placeholder="Search communities..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            wrapperClassName="md:w-[34rem] lg:w-[48rem]"
          />
          <Select<CommunityVisibility>
            size="sm"
            aria-label="Filter by visibility"
            options={visibilityFilterOptions}
            value={visibilityFilter}
            onChange={setVisibilityFilter}
            placeholder="Filter by visibility"
            className="sm:w-52"
          />
          <Button variant="secondary" size="sm" onClick={clearFilters} className="h-9">
            Clear filters
          </Button>
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
