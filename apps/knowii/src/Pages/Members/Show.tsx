import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, MemberProfile, SocialMediaLinkProperty, SpaceGroup } from '@knowii/common';
import {
  COMMUNITY_URL,
  knowiiApiClient,
  MESSAGES_URL,
  useAppData,
  safeHttpUrl,
  SOCIAL_MEDIA_LINK_ICONS,
  SOCIAL_MEDIA_LINK_NAMES,
  socialMediaLinkPropertySchema,
} from '@knowii/common';
import { FaBan, FaEnvelope, FaLocationDot, FaPhone } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { Avatar, Button, buttonClasses, Card, RelativeTime } from '@/Components/ui';

interface Props {
  // WARNING: aligned with MemberController.php
  community: Community;
  spaceGroups: SpaceGroup[];
  member: MemberProfile;
  // Direct messages (#1186)
  messaging: { isSelf: boolean; blockedByMe: boolean; canMessage: boolean };
}

/**
 * The profile of a member, as the other members of the community see it (mentions link here).
 */
export default function MemberPage({ community, spaceGroups, member, messaging }: Props) {
  const { showToast } = useAppData();
  const [busy, setBusy] = useState(false);

  const toggleBlock = async () => {
    setBusy(true);
    const result = messaging.blockedByMe
      ? await knowiiApiClient.users.blocks.unblock(member.cuid)
      : await knowiiApiClient.users.blocks.block(member.cuid);
    setBusy(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: messaging.blockedByMe ? `${member.name} is unblocked` : `${member.name} is blocked` });
    router.reload({ only: ['messaging'] });
  };

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: member.name },
  ];

  // Only known link types with an http(s) URL: the values are written by the member
  const links = Object.entries(member.social_links).flatMap(([property, value]) => {
    const parsed = socialMediaLinkPropertySchema.safeParse(property);
    const url = safeHttpUrl(value);
    return parsed.success && url ? [{ property: parsed.data as SocialMediaLinkProperty, url }] : [];
  });

  return (
    <AppLayout browserPageTitle={`${member.name} - ${community.name}`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={spaceGroups} contentWidth="readable">
        <Card as="article" aria-labelledby="member-name" className="flex flex-col gap-4 p-6">
          <header className="flex items-center gap-4">
            <Avatar name={member.name} imageUrl={member.avatar_url} size="lg" />
            <div className="min-w-0">
              <h1 id="member-name" className="text-2xl font-extrabold">
                {member.name}
              </h1>
              <p className="text-muted">@{member.username}</p>
            </div>
          </header>

          <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
            {member.phone && (
              <a href={`tel:${member.phone.replace(/[^+\d]/g, '')}`} className="flex items-center gap-1 hover:underline">
                <FaPhone aria-hidden="true" />
                {member.phone}
              </a>
            )}
            {member.location && (
              <span className="flex items-center gap-1">
                <FaLocationDot aria-hidden="true" />
                {member.location}
              </span>
            )}
            <span>
              Member since <RelativeTime date={member.member_since} />
            </span>
          </p>

          {!messaging.isSelf && (
            <div className="flex flex-wrap gap-2">
              {messaging.canMessage && (
                <Link
                  href={`${route(MESSAGES_URL, { communitySlug: community.slug })}?to=${encodeURIComponent(member.username)}`}
                  className={buttonClasses({ variant: 'primary', size: 'sm' })}
                >
                  <FaEnvelope aria-hidden="true" />
                  Send a message
                </Link>
              )}
              <Button variant="ghost" size="sm" onClick={() => void toggleBlock()} loading={busy} aria-describedby="member-block-hint">
                <FaBan aria-hidden="true" />
                {messaging.blockedByMe ? 'Unblock' : 'Block'}
              </Button>
              <p id="member-block-hint" className="sr-only">
                A blocked member cannot write to you, and you cannot write to them. They are not told.
              </p>
            </div>
          )}

          {member.bio && <p className="whitespace-pre-line">{member.bio}</p>}

          {links.length > 0 && (
            <section aria-labelledby="member-links">
              <h2 id="member-links" className="mb-2 text-sm font-bold text-muted">
                Links
              </h2>
              <ul className="flex flex-wrap gap-2">
                {links.map(({ property, url }) => {
                  const Icon = SOCIAL_MEDIA_LINK_ICONS[property];
                  return (
                    <li key={property}>
                      <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-primary"
                      >
                        <Icon aria-hidden="true" />
                        {SOCIAL_MEDIA_LINK_NAMES[property]}
                        <span className="sr-only"> (opens in a new tab)</span>
                      </a>
                    </li>
                  );
                })}
              </ul>
            </section>
          )}
        </Card>
      </CommunityShell>
    </AppLayout>
  );
}
