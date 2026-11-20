import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, CommunityMember, MemberDirectorySort, Paginated, SpaceGroup } from '@knowii/common';
import {
  COMMUNITY_MEMBERS_PAGE_SIZE,
  COMMUNITY_ROLE_LABELS,
  COMMUNITY_SETTINGS_URL,
  COMMUNITY_URL,
  HttpStatus,
  knowiiApiClient,
  MEMBER_SEARCH_MAX_LENGTH,
  MEMBER_URL,
  MEMBERS_URL,
} from '@knowii/common';
import { FaGear, FaMagnifyingGlass, FaUsers } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import {
  Avatar,
  Badge,
  Button,
  Card,
  EmptyState,
  LinkButton,
  LinkTabs,
  LoadMoreButton,
  RelativeTime,
  TextInput,
  cn,
  useFocusAfterRender,
} from '@/Components/ui';

/**
 * The words of the member directory (#148), in one place. A word changes here, and nowhere else. The spec reads these
 * values.
 */
export const MEMBER_DIRECTORY_WORDS = {
  title: 'Members',
  manageMembers: 'Manage members',
  // The name of the search landmark and of its field, and the placeholder: the words of the member management
  searchLabel: 'Search the members',
  searchPlaceholder: 'Search by name or username',
  sortLabel: 'Sort the members',
  sortName: 'A to Z',
  sortNewest: 'Newest',
  listLabel: 'Members',
  joined: 'Joined',
  // The status line without a search text: "128 members", "1 member"
  count: (count: number) => (count === 1 ? '1 member' : `${count} members`),
  // The status line with a search text: "3 members found", "1 member found", "No member found"
  found: (count: number) => (count === 0 ? 'No member found' : `${count === 1 ? '1 member' : `${count} members`} found`),
  // After "Load more": the real number of new entries
  loaded: (count: number) => `${count} more loaded`,
  // The server refused a search, a sort or "Load more": the member read too many lists in a minute
  tooManySearches: 'Too many searches. Wait a minute, then try again.',
  emptyTitle: 'No member matches this search',
  clearSearch: 'Clear the search',
  loadMore: 'Load more',
} as const;

// The pause in the typing after which the list follows the search text
const SEARCH_DELAY_MS = 250;

// The props that a search asks for: the community and its navigation stay
const LIST_PROPS = ['members', 'sort', 'query'];

/**
 * An answer of the server that is not a page, as the router gives it to the page
 */
interface RefusedAnswer {
  status: number;
}

interface Props {
  // WARNING: aligned with MemberController::index()
  community: Community;
  spaceGroups: SpaceGroup[];
  /**
   * The first entries of the list, in the order of `sort`, for the search text `query`
   */
  members: Paginated<CommunityMember>;
  sort: MemberDirectorySort;
  /**
   * The search text as the server read it: trimmed, and cut to its maximum length
   */
  query: string;
  /**
   * The reader can add members: the page shows the link to the member management of the settings
   */
  canManageMembers: boolean;
}

/**
 * What the list shows: the first entries of the server, and the ones that "Load more" added. `source` is the prop that
 * the entries started from: a new one (a search, another sort, a reload) starts the list again.
 */
interface ShownList {
  source: Paginated<CommunityMember>;
  items: CommunityMember[];
  nextOffset: number | null;
  /**
   * The number of members of the whole list, as the last answer of the server gave it
   */
  totalCount: number;
  /**
   * The number of entries that the last "Load more" added, for the status line. Null when it added none
   */
  loaded: number | null;
  error: string | null;
}

const listOf = (members: Paginated<CommunityMember>): ShownList => ({
  source: members,
  items: members.items,
  nextOffset: members.pagination.nextOffset,
  totalCount: members.pagination.totalCount,
  loaded: null,
  error: null,
});

/**
 * The member directory of a community (#148, ADR 0056): the members, for the members, with a search by name or username,
 * two sorts and "Load more". Each name opens the member page.
 *
 * The address holds the sort and the search text, so a reload, the back button and a shared link give the same list.
 * The server gives the first entries with the page. A pause of 250 ms in the typing visits the page again for the
 * three props of the list only, in place of the entry of the history, and keeps the state of the page: the field keeps
 * the focus and its text. The two sorts are links: a press is a full visit that keeps the state of the page, so the
 * pressed one keeps the focus. "Load more" reads the API list of the members with the sort and the search text of the
 * list that shows.
 *
 * The server limits the member lists per minute. When it refuses a search or a sort (429), the list stays as it is and
 * an alert line says to wait; the line goes away at the next visit that succeeds. "Load more" says the same in its
 * own error line.
 */
export default function MembersPage({ community, spaceGroups, members, sort, query, canManageMembers }: Props) {
  const [searchText, setSearchText] = useState(query);
  const [list, setList] = useState<ShownList>(() => listOf(members));
  if (list.source !== members) {
    // The server gave a new first page (a search, another sort, a reload): the entries that were loaded belong to the old one
    setList(listOf(members));
  }
  const [loadingMore, setLoadingMore] = useState(false);
  const [visiting, setVisiting] = useState(false);
  // The server refused the last search or sort: too many member lists in a minute
  const [tooManySearches, setTooManySearches] = useState(false);
  const searchRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  // The first page that shows now: an answer of "Load more" for another one is dropped
  const sourceRef = useRef(members);
  useEffect(() => {
    sourceRef.current = members;
  }, [members]);
  // The sort that the member chose last: the one that shows, or the one of a visit of the directory that is still on
  // its way (a press on a sort). A search that waited for the end of the pause reads it when it starts, so it never
  // brings back the sort of the time of the key
  const sortRef = useRef(sort);
  useEffect(() => {
    sortRef.current = sort;
  }, [sort]);
  const focusAfterRender = useFocusAfterRender();

  const directoryUrl = route(MEMBERS_URL, { communitySlug: community.slug });
  const addressOf = (order: MemberDirectorySort, text: string) => {
    const parameters = new URLSearchParams({ sort: order });
    if (text !== '') {
      parameters.set('query', text);
    }
    return `${directoryUrl}?${parameters.toString()}`;
  };

  const cancelPendingSearch = useCallback(() => {
    if (searchTimer.current !== null) {
      clearTimeout(searchTimer.current);
      searchTimer.current = null;
    }
  }, []);
  useEffect(() => cancelPendingSearch, [cancelPendingSearch]);

  /**
   * The answer 429 of a visit of the directory: the list stays as it is, and the page says to wait. `false` tells the
   * router that the page handled the answer, so the router does not show the answer of the server in a dialog.
   */
  const refuseTooManySearches = useCallback((response: RefusedAnswer): false | undefined => {
    if (response.status !== HttpStatus.TOO_MANY_REQUESTS) {
      return undefined;
    }
    setTooManySearches(true);
    return false;
  }, []);

  // The address of the visit that runs: a refused answer is for this page only when the visit was a visit of the directory
  const visitedPath = useRef<string | null>(null);
  const directoryPath = new URL(directoryUrl, window.location.href).pathname;
  useEffect(() => {
    // The list is busy while a visit of this page runs: a search, or a press on a sort
    const stopStart = router.on('start', (event) => {
      const { url } = event.detail.visit;
      visitedPath.current = url.pathname;
      // A press on a sort: a search that starts before its answer comes keeps the pressed sort
      const chosen = url.searchParams.get('sort');
      if (url.pathname === directoryPath && (chosen === 'name' || chosen === 'newest')) {
        sortRef.current = chosen;
      }
      setVisiting(true);
    });
    const stopFinish = router.on('finish', () => setVisiting(false));
    // A press on a sort is a link of the toolkit, which takes no handler: the page listens to the router for it. The
    // same listener covers a reload of the page after an event of the community
    const stopRefused = router.on('httpException', (event) =>
      visitedPath.current === directoryPath ? refuseTooManySearches(event.detail.response) : undefined,
    );
    const stopSuccess = router.on('success', () => setTooManySearches(false));
    return () => {
      stopStart();
      stopFinish();
      stopRefused();
      stopSuccess();
    };
  }, [directoryPath, refuseTooManySearches]);

  /**
   * Visit the page for a search text, with the sort that the member chose last at the time of the visit. Only the list
   * comes back, the entry of the history is replaced, and the state of the page stays: the field keeps the focus and
   * what the person typed.
   */
  const search = (text: string) => {
    cancelPendingSearch();
    router.get(
      directoryUrl,
      { sort: sortRef.current, ...(text === '' ? {} : { query: text }) },
      { replace: true, preserveState: true, preserveScroll: true, only: LIST_PROPS, onHttpException: refuseTooManySearches },
    );
  };

  const changeSearchText = (text: string) => {
    setSearchText(text);
    cancelPendingSearch();
    searchTimer.current = setTimeout(() => {
      searchTimer.current = null;
      search(text.trim());
    }, SEARCH_DELAY_MS);
  };

  const clearSearch = () => {
    setSearchText('');
    search('');
    searchRef.current?.focus();
  };

  // The link of the name of an entry. The exact value is compared: no selector is built from an identifier
  const linkOf = (memberCuid: string) =>
    Array.from(listRef.current?.querySelectorAll<HTMLElement>('[data-member-link]') ?? []).find(
      (link) => link.dataset['memberLink'] === memberCuid,
    );

  const loadMore = async () => {
    if (list.nextOffset === null || loadingMore) {
      return;
    }
    const source = members;
    setLoadingMore(true);
    setList((current) => (current.source === source ? { ...current, loaded: null, error: null } : current));
    const result = await knowiiApiClient.communities.members.list(
      community.cuid,
      COMMUNITY_MEMBERS_PAGE_SIZE,
      list.nextOffset,
      query,
      sort,
    );
    setLoadingMore(false);
    if (sourceRef.current !== source) {
      // Another list opened meanwhile: this answer is not for it
      return;
    }
    if (!result.ok) {
      // Above the button, which stays and is the retry: the wait when the server refused the request (too many member
      // lists in a minute), else the message of the API client
      const error = result.status === HttpStatus.TOO_MANY_REQUESTS ? MEMBER_DIRECTORY_WORDS.tooManySearches : result.message;
      setList((current) => (current.source === source ? { ...current, error } : current));
      return;
    }
    // A member who joins or leaves between two pages can move one entry: skip the ones that show already
    const added = result.data.items.filter((member) => !list.items.some((shown) => shown.cuid === member.cuid));
    const { nextOffset, totalCount } = result.data.pagination;
    setList((current) =>
      current.source === source
        ? {
            ...current,
            items: [...current.items, ...added.filter((member) => !current.items.some((shown) => shown.cuid === member.cuid))],
            nextOffset,
            // The count of this answer: a member who joined or left since the first page changes it
            totalCount,
            // No entry was added (the members of the next page left meanwhile): the status says only the count
            loaded: added.length > 0 ? added.length : null,
          }
        : current,
    );
    const first = added[0];
    const last = list.items[list.items.length - 1];
    if (first) {
      // The button may go away with the last page: the reader continues on the first new name
      focusAfterRender(() => linkOf(first.cuid));
    } else if (nextOffset === null && last) {
      // The button goes away and no entry came: the focus would be lost, so the reader continues on the last name
      focusAfterRender(() => linkOf(last.cuid));
    }
  };

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: MEMBER_DIRECTORY_WORDS.title, icon: <FaUsers aria-hidden="true" /> },
  ];

  const total = list.totalCount;
  // The sort links keep the search text that the field shows
  const typed = searchText.trim();

  return (
    <AppLayout
      browserPageTitle={`${MEMBER_DIRECTORY_WORDS.title} - ${community.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <CommunityShell community={community} spaceGroups={spaceGroups}>
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h1 className="text-2xl font-extrabold">{MEMBER_DIRECTORY_WORDS.title}</h1>
            {canManageMembers && (
              <LinkButton variant="secondary" size="sm" href={route(COMMUNITY_SETTINGS_URL, { communitySlug: community.slug })}>
                <FaGear aria-hidden="true" />
                {MEMBER_DIRECTORY_WORDS.manageMembers}
              </LinkButton>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
            <form
              role="search"
              aria-label={MEMBER_DIRECTORY_WORDS.searchLabel}
              // Enter in the field searches at once
              onSubmit={(event) => {
                event.preventDefault();
                search(searchText.trim());
              }}
              className="w-full @2xl:w-80"
            >
              <TextInput
                ref={searchRef}
                type="search"
                value={searchText}
                onChange={(event) => changeSearchText(event.target.value)}
                maxLength={MEMBER_SEARCH_MAX_LENGTH}
                placeholder={MEMBER_DIRECTORY_WORDS.searchPlaceholder}
                aria-label={MEMBER_DIRECTORY_WORDS.searchLabel}
                autoComplete="off"
                icon={<FaMagnifyingGlass aria-hidden="true" />}
              />
            </form>
            {/* A press on a sort wins over a search that waits for the end of the pause: the link holds the same text */}
            <div onClickCapture={cancelPendingSearch} className="min-w-0">
              <LinkTabs
                ariaLabel={MEMBER_DIRECTORY_WORDS.sortLabel}
                items={[
                  { label: MEMBER_DIRECTORY_WORDS.sortName, href: addressOf('name', typed), current: sort === 'name' },
                  { label: MEMBER_DIRECTORY_WORDS.sortNewest, href: addressOf('newest', typed), current: sort === 'newest' },
                ]}
              />
            </div>
          </div>

          {/* "128 members", "3 members found", then "20 more loaded": polite, and always in the page so that a screen
              reader reads each change */}
          <p role="status" aria-live="polite" className="text-sm text-muted">
            <span>{query === '' ? MEMBER_DIRECTORY_WORDS.count(total) : MEMBER_DIRECTORY_WORDS.found(total)}</span>
            {list.loaded !== null && <span>{`. ${MEMBER_DIRECTORY_WORDS.loaded(list.loaded)}`}</span>}
          </p>

          {/* The server refused the last search or sort: the list below is the one of before */}
          {tooManySearches && <AlertLine>{MEMBER_DIRECTORY_WORDS.tooManySearches}</AlertLine>}

          {list.items.length === 0 ? (
            <EmptyState
              icon={<FaUsers />}
              title={MEMBER_DIRECTORY_WORDS.emptyTitle}
              action={
                <Button variant="secondary" onClick={clearSearch}>
                  {MEMBER_DIRECTORY_WORDS.clearSearch}
                </Button>
              }
            />
          ) : (
            <ul ref={listRef} aria-label={MEMBER_DIRECTORY_WORDS.listLabel} aria-busy={visiting} className="grid gap-3 @3xl:grid-cols-2">
              {list.items.map((member) => (
                <li key={member.cuid} className="min-w-0">
                  <MemberEntry member={member} href={route(MEMBER_URL, { communitySlug: community.slug, username: member.username })} />
                </li>
              ))}
            </ul>
          )}

          {list.error !== null && <AlertLine className="text-center">{list.error}</AlertLine>}

          {list.nextOffset !== null && list.items.length > 0 && (
            <LoadMoreButton onClick={() => void loadMore()} loading={loadingMore}>
              {MEMBER_DIRECTORY_WORDS.loadMore}
            </LoadMoreButton>
          )}
        </div>
      </CommunityShell>
    </AppLayout>
  );
}

/**
 * A line that a screen reader says when it shows: the refusal of a search or of a sort, and the failure of "Load more"
 */
function AlertLine({ children, className }: { children: string; className?: string }) {
  return (
    <p role="alert" className={cn('text-sm text-danger-text', className)}>
      {children}
    </p>
  );
}

interface MemberEntryProps {
  member: CommunityMember;
  /**
   * The address of the member page
   */
  href: string;
}

/**
 * An entry of the directory, in the order that a screen reader gives: the picture (a decoration: the name is next to
 * it), the name as the only link, the username, the headline when the member shows one (#1688), the role of the owner,
 * of an admin and of a moderator as a text label, and the date of joining. A long name and a long username are cut; the
 * headline wraps.
 */
function MemberEntry({ member, href }: MemberEntryProps) {
  return (
    <Card as="div" className="flex h-full items-start gap-3 p-3">
      <Avatar name={member.name} imageUrl={member.avatar_url} size="lg" />
      <div className="flex min-w-0 flex-1 flex-col">
        <Link
          href={href}
          data-member-link={member.cuid}
          className="touch-target max-w-full min-w-0 self-start rounded font-bold hover:underline focus-visible:outline-2 focus-visible:outline-primary"
        >
          <span className="truncate">{member.name}</span>
        </Link>
        <span className="truncate text-sm text-muted">@{member.username}</span>
        {/* Plain text: never HTML, Markdown or a link. A long word breaks, and nothing is cut */}
        {member.headline && <p className="mt-1 text-sm wrap-anywhere">{member.headline}</p>}
        <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted empty:hidden">
          {member.role !== 'member' && <Badge variant="neutral">{COMMUNITY_ROLE_LABELS[member.role]}</Badge>}
          {member.member_since && (
            <span>
              {MEMBER_DIRECTORY_WORDS.joined} <RelativeTime date={member.member_since} />
            </span>
          )}
        </p>
      </div>
    </Card>
  );
}
