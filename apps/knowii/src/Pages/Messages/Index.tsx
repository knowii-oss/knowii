import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Author, ChatMessage, Community, Conversation, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, CONVERSATION_URL, knowiiApiClient, MESSAGES_URL, useSocket, useTypedPage } from '@knowii/common';
import { FaArrowLeft, FaEnvelope, FaPenToSquare } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { ConversationList } from '@/Components/Messages/ConversationList';
import { ConversationView } from '@/Components/Messages/ConversationView';
import { NewConversationDialog } from '@/Components/Messages/NewConversationDialog';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, cn, EmptyState } from '@/Components/ui';

interface Props {
  // WARNING: aligned with ConversationController.php
  community: Community;
  spaceGroups: SpaceGroup[];
  conversations: Conversation[];
  conversation: Conversation | null;
  messages: { items: ChatMessage[]; has_more: boolean; first_unread_cuid: string | null } | null;
  permissions: { canStart: boolean; canSend: boolean };
  blockedByMe: boolean;
}

/**
 * The direct messages of the member in a community (#1186): the conversations next to the open one on large screens,
 * one or the other on phones. "New message" writes to one or more members; `?to=username` opens it for that member.
 */
export default function MessagesPage(props: Props) {
  const { community, conversation } = props;
  const page = useTypedPage();
  const user = page.props.auth.user;
  const currentUserCuid = user?.cuid ?? '';
  const [dialogOpen, setDialogOpen] = useState(false);
  // A new key at each opening: the dialog starts empty, or with the member of ?to=
  const [dialogKey, setDialogKey] = useState(0);
  const [initialMembers, setInitialMembers] = useState<Author[]>([]);
  const openDialog = (members: Author[]) => {
    setInitialMembers(members);
    setDialogKey((key) => key + 1);
    setDialogOpen(true);
  };

  // "Send a message" on a member page: ?to=username chooses that member
  useEffect(() => {
    const username = new URLSearchParams(window.location.search).get('to');
    if (!username || !props.permissions.canStart) {
      return;
    }
    void knowiiApiClient.communities.members.search(community.cuid, username, 5).then((members) => {
      const member = members.find((item) => item.username.toLowerCase() === username.toLowerCase());
      if (member && member.cuid !== currentUserCuid) {
        openDialog([member]);
      }
    });
  }, [community.cuid, currentUserCuid, props.permissions.canStart]);

  // A message in any conversation of the member: the list and the unread counts follow. The open conversation marks its
  // messages as read first, so its reload waits a little.
  const reloadList = useCallback(() => router.reload({ only: ['conversations', 'community'] }), []);
  const userChannel = useMemo(() => ({ type: 'user' as const, userCuid: currentUserCuid }), [currentUserCuid]);
  useSocket({
    channel: userChannel,
    event: ['conversation.message.created', 'conversation.message.deleted'],
    callback: (_event, payload) => {
      if (payload.conversation_cuid === conversation?.cuid) {
        setTimeout(reloadList, 1500);
      } else {
        reloadList();
      }
    },
  });

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    conversation
      ? { label: 'Messages', href: route(MESSAGES_URL, { communitySlug: community.slug }), icon: <FaEnvelope aria-hidden="true" /> }
      : { label: 'Messages', icon: <FaEnvelope aria-hidden="true" /> },
  ];

  return (
    <AppLayout browserPageTitle={`Messages - ${community.name}`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={props.spaceGroups}>
        <h1 className="sr-only">Messages</h1>
        <div className="grid gap-6 @3xl:grid-cols-[20rem_1fr]">
          <section aria-labelledby="conversations-title" className={cn('flex min-w-0 flex-col gap-3', conversation && 'hidden @3xl:flex')}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 id="conversations-title" className="text-lg font-extrabold">
                Conversations
              </h2>
              {props.permissions.canStart && (
                <Button size="sm" onClick={() => openDialog([])}>
                  <FaPenToSquare aria-hidden="true" />
                  New message
                </Button>
              )}
            </div>
            {!props.permissions.canStart && <p className="text-sm text-muted">Direct messages are turned off in this community.</p>}
            <nav aria-labelledby="conversations-title">
              <ConversationList
                communitySlug={community.slug}
                conversations={props.conversations}
                activeCuid={conversation?.cuid ?? null}
                currentUserCuid={currentUserCuid}
              />
            </nav>
          </section>

          <div className={cn('min-w-0', !conversation && 'hidden @3xl:block')}>
            {conversation && props.messages ? (
              <div className="flex flex-col gap-2">
                <Link
                  href={route(MESSAGES_URL, { communitySlug: community.slug })}
                  className="touch-target flex items-center gap-2 text-sm text-muted hover:text-primary @3xl:hidden"
                >
                  <FaArrowLeft aria-hidden="true" />
                  All conversations
                </Link>
                <ConversationView
                  communityCuid={community.cuid}
                  communitySlug={community.slug}
                  conversation={conversation}
                  messages={props.messages}
                  currentUserCuid={currentUserCuid}
                  canSend={props.permissions.canSend}
                  blockedByMe={props.blockedByMe}
                  directMessagesEnabled={props.permissions.canStart}
                  showsOnlineStatus={user?.show_online_status !== false}
                />
              </div>
            ) : (
              <EmptyState
                icon="✉️"
                title="Your direct messages"
                description="Choose a conversation, or write to one or more members with New message. Only the members of a conversation read it."
              />
            )}
          </div>
        </div>
      </CommunityShell>

      {user && (
        <NewConversationDialog
          key={dialogKey}
          open={dialogOpen}
          communityCuid={community.cuid}
          currentUserCuid={currentUserCuid}
          initialMembers={initialMembers}
          onClose={() => setDialogOpen(false)}
          onStarted={(started) => {
            setDialogOpen(false);
            router.visit(route(CONVERSATION_URL, { communitySlug: community.slug, conversationCuid: started.cuid }));
          }}
        />
      )}
    </AppLayout>
  );
}
