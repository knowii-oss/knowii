import type { NoAccessPageProps } from '@knowii/common';
import { COMMUNITY_URL, DASHBOARD_URL } from '@knowii/common';
import { FaLock } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { JoinCommunity } from '@/Components/Communities/JoinCommunity';
import {
  NO_ACCESS_BY_INVITATION,
  NO_ACCESS_GENERAL_REASON,
  NO_ACCESS_HOME_LINK,
  NO_ACCESS_PUBLIC_PAGE_LINK,
  NO_ACCESS_TITLE,
  noAccessReason,
} from '@/Components/Communities/no-access-texts';
import type { BreadcrumbItem } from '@/Components/ui';
import { LinkButton, NoAccessPanel } from '@/Components/ui';

// The link and the button of the next step: 44 px high on a phone (WCAG 2.5.8, like the other links outside running text)
const NEXT_STEP_TARGET = 'touch-target';

/**
 * The page without access (#1561): a signed-in person who is not a member opens a page of a community that is for its
 * members. The answer stays a 403. The server sends the community (its public shape) only when this person may see its
 * public page: then the page names it and offers the next step ("Join" for an open community, a link to its public page
 * for a community by invitation). Else it names nothing of the community, and leads to the communities of the person.
 */
export default function NoAccessPage({ kind, community }: NoAccessPageProps) {
  const breadcrumbItems: BreadcrumbItem[] = community
    ? [
        {
          label: community.name,
          href: route(COMMUNITY_URL, { communitySlug: community.slug }),
          icon: <CommunityIcon visibility={community.visibility} />,
        },
      ]
    : [];

  return (
    <AppLayout browserPageTitle={NO_ACCESS_TITLE} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()} contentWidth="form">
      <NoAccessPanel
        icon={<FaLock />}
        title={NO_ACCESS_TITLE}
        reason={community ? noAccessReason(kind, community.name) : NO_ACCESS_GENERAL_REASON}
        className="mx-auto mt-6 max-w-3xl"
      >
        {community && community.join_policy === 'open' && (
          // The join reloads the same address: the server then answers the content of the page (#1486)
          <div className="[&_button]:min-h-11 md:[&_button]:min-h-0">
            <JoinCommunity community={community} />
          </div>
        )}
        {community && community.join_policy !== 'open' && (
          <>
            <p className="text-base">{NO_ACCESS_BY_INVITATION}</p>
            <LinkButton href={route(COMMUNITY_URL, { communitySlug: community.slug })} variant="secondary" className={NEXT_STEP_TARGET}>
              {NO_ACCESS_PUBLIC_PAGE_LINK}
            </LinkButton>
          </>
        )}
        {!community && (
          <LinkButton href={route(DASHBOARD_URL)} variant="secondary" className={NEXT_STEP_TARGET}>
            {NO_ACCESS_HOME_LINK}
          </LinkButton>
        )}
      </NoAccessPanel>
    </AppLayout>
  );
}
