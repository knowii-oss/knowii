import { Link } from '@inertiajs/react';
import type { Community, NewestPost, SpaceGroup } from '@knowii/common';
import { LOGIN_URL, POST_URL, REGISTER_URL, useTypedPage } from '@knowii/common';
import { FaRegNewspaper } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { Avatar, Card, CardBody, CardHeader, LinkButton, RelativeTime } from '@/Components/ui';

interface Props {
  // The public shape: no member count, no rights, no settings (#1485)
  community: Community;
  // The public spaces only, never a chat space
  spaceGroups: SpaceGroup[];
  // The newest posts of the public discussion spaces, 10 at most
  newestPosts: NewestPost[];
}

/**
 * The public page of a community (#1485, ADR 0044), for a visitor and for a signed-in person who is not a member: the
 * name, the description, the navigation of the public spaces and the newest public posts. No team, no member count and
 * no member list: the server sends none of it.
 */
export default function PublicCommunityPage({ community, spaceGroups, newestPosts }: Props) {
  const isVisitor = !useTypedPage().props.auth.user;

  const breadcrumbItems: BreadcrumbItem[] = [{ label: community.name, icon: <CommunityIcon visibility={community.visibility} /> }];

  return (
    <AppLayout browserPageTitle={community.name} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={spaceGroups} contentWidth="readable">
        <div className="flex flex-col gap-6">
          <section aria-labelledby="community-name" className="flex flex-col gap-3">
            <h1 id="community-name" className="text-2xl font-extrabold tracking-tight sm:text-3xl">
              {community.name}
            </h1>
            {community.description && <p className="text-base text-muted">{community.description}</p>}
            <div className="flex flex-wrap items-center gap-3">
              {isVisitor && (
                <>
                  <LinkButton href={route(LOGIN_URL)} variant="secondary">
                    Sign in
                  </LinkButton>
                  <LinkButton href={route(REGISTER_URL)}>Sign up</LinkButton>
                </>
              )}
              {/* The "Join" button of the community goes here (#1486) */}
              <div id="community-join-slot" data-slot="join" />
            </div>
          </section>

          <Card aria-labelledby="newest-posts-title" className="overflow-hidden">
            <CardHeader title={<span id="newest-posts-title">Newest posts</span>} icon={<FaRegNewspaper aria-hidden="true" />} />
            <CardBody>
              {newestPosts.length > 0 ? (
                <ul className="flex flex-col gap-4">
                  {newestPosts.map((post) => (
                    <li key={post.cuid}>
                      <article aria-labelledby={`newest-post-${post.cuid}`} className="flex flex-col gap-1">
                        <h2 id={`newest-post-${post.cuid}`} className="text-lg font-extrabold">
                          <Link
                            href={route(POST_URL, { communitySlug: community.slug, spaceSlug: post.space.slug, postSlug: post.slug })}
                            className="rounded hover:underline focus-visible:outline-2 focus-visible:outline-primary"
                          >
                            {post.title}
                          </Link>
                        </h2>
                        {post.excerpt && <p className="line-clamp-3 text-sm">{post.excerpt}</p>}
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                          {post.author ? (
                            <span className="flex items-center gap-2">
                              <Avatar name={post.author.name} imageUrl={post.author.avatar_url} size="sm" />
                              <span>
                                {post.author.name} <span className="sr-only">, username</span>
                                <span aria-hidden="true">@</span>
                                {post.author.username}
                              </span>
                            </span>
                          ) : (
                            <span>Deleted member</span>
                          )}
                          <span aria-hidden="true">·</span>
                          <span>
                            {post.space.emoji && <span aria-hidden="true">{post.space.emoji} </span>}
                            {post.space.name}
                          </span>
                          <span aria-hidden="true">·</span>
                          <RelativeTime date={post.created_at} />
                        </p>
                      </article>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-muted">No posts yet.</p>
              )}
            </CardBody>
          </Card>
        </div>
      </CommunityShell>
    </AppLayout>
  );
}
