import {
  COMMUNITY_API_BASE_PATH,
  USER_AVATAR_API_PATH,
  USER_BLOCKS_API_PATH,
  CONVERSATION_API_PARAM_CONVERSATION,
  COMMUNITY_CONVERSATIONS_API_PATH,
  COMMUNITY_CONVERSATION_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGES_API_PATH,
  COMMUNITY_CONVERSATION_READ_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGE_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGE_REACTIONS_API_PATH,
  USER_ONLINE_STATUS_API_PATH,
  COMMUNITY_ONLINE_MEMBERS_API_PATH,
  COMMUNITY_SPACE_ONLINE_MEMBERS_API_PATH,
  COMMUNITY_API_PATH,
  COMMUNITY_API_PATH_PARAM_COMMUNITY,
  COMMUNITY_SPACE_API_PATH,
  COMMUNITY_SPACE_RESOURCE_API_PATH,
  COMMUNITY_SPACE_RESOURCE_CAPTURE_API_PATH,
  COMMUNITY_SPACE_RESOURCE_COMMENT_API_PATH,
  COMMUNITY_SPACE_RESOURCE_COMMENTS_API_PATH,
  COMMUNITY_SPACE_RESOURCES_API_PATH,
  COMMUNITY_MEMBERS_API_PATH,
  COMMUNITY_MEMBER_API_PATH,
  COMMUNITY_API_PARAM_MEMBER,
  COMMUNITY_INVITATIONS_API_PATH,
  COMMUNITY_INVITATION_API_PATH,
  COMMUNITY_API_PARAM_INVITATION,
  COMMUNITY_MODERATOR_PERMISSIONS_API_PATH,
  COMMUNITY_ACCESS_GROUPS_API_PATH,
  COMMUNITY_ACCESS_GROUP_API_PATH,
  COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH,
  COMMUNITY_ACCESS_GROUP_MEMBER_API_PATH,
  COMMUNITY_API_PARAM_ACCESS_GROUP,
  COMMUNITY_UPLOADS_API_PATH,
  COMMUNITY_SPACE_GROUP_API_PATH,
  COMMUNITY_SPACE_GROUP_SPACES_REORDER_API_PATH,
  COMMUNITY_SPACE_GROUPS_API_PATH,
  COMMUNITY_SPACE_GROUPS_REORDER_API_PATH,
  COMMUNITY_SPACES_API_PATH,
  COMMUNITY_SPACE_MESSAGE_API_PATH,
  COMMUNITY_SPACE_MESSAGE_REACTIONS_API_PATH,
  COMMUNITY_SPACE_MESSAGE_THREAD_API_PATH,
  COMMUNITY_SPACE_MESSAGES_API_PATH,
  COMMUNITY_SPACE_MESSAGES_READ_API_PATH,
  COMMUNITY_SPACE_PAGE_API_PATH,
  COMMUNITY_SPACE_PAGES_API_PATH,
  COMMUNITY_SPACE_PAGES_ORDER_API_PATH,
  COMMUNITY_SPACE_POST_API_PATH,
  COMMUNITY_SPACE_POST_COMMENT_API_PATH,
  COMMUNITY_SPACE_POST_COMMENTS_API_PATH,
  SPACE_API_PARAM_COMMENT,
  COMMUNITY_SPACE_POST_PIN_API_PATH,
  COMMUNITY_SPACE_POSTS_API_PATH,
  MARKDOWN_PREVIEW_API_PATH,
  SPACE_API_PARAM_MESSAGE,
  SPACE_API_PARAM_PAGE,
  SPACE_API_PARAM_POST,
  SPACE_API_PARAM_RESOURCE,
  PING_API_PATH,
  SPACE_API_PARAM_COMMUNITY,
  SPACE_API_PARAM_SPACE,
  SPACE_API_PARAM_SPACE_GROUP,
  USERS_API_IS_USERNAME_AVAILABLE_PATH,
  NEWSLETTER_API_PATH,
  WAITLIST_API_PATH,
} from '../constants';
import type { PingResponse } from './ping-response.schema';
import { pingResponseSchema } from './ping-response.schema';
import type { CreateCommunityResponse } from './communities/create-community-response.schema';
import { createCommunityResponseSchema } from './communities/create-community-response.schema';
import type { CreateCommunityRequest } from './communities/create-community-request.schema';
import type { IsUsernameAvailableRequest } from './users/is-username-available-request.schema';
import type { IsUsernameAvailableResponse } from './users/is-username-available-response.schema';
import { isUsernameAvailableResponseSchema } from './users/is-username-available-response.schema';
import type { DeleteCommunityRequest } from './communities/delete-community-request.schema';
import type { JoinWaitlistRequest } from './waitlist/join-waitlist-request.schema';
import type { JoinWaitlistResponse } from './waitlist/join-waitlist-response.schema';
import { joinWaitlistResponseSchema } from './waitlist/join-waitlist-response.schema';
import { z } from 'zod';
import type { AccessGroup, AccessGroupForm, AccessGroupMemberInput } from '../types/access-group.schema';
import { accessGroupSchema } from '../types/access-group.schema';
import { HttpStatus } from '../types/http-status.intf';
import type { ApiResult } from './api-result';
import { apiPath, apiRequest, fieldErrors } from './api-result';
import type { Space, SpaceCreateInput, SpaceForm, SpaceGroup, SpaceGroupForm } from '../types/space.schema';
import { spaceGroupSchema, spaceSchema } from '../types/space.schema';
import type { Author, Paginated, Post, PostForm } from '../types/post.schema';
import { authorSchema, paginationSchema, postSchema } from '../types/post.schema';
import type { Comment, CommentForm } from '../types/comment.schema';
import { commentSchema } from '../types/comment.schema';
import type { Conversation, NewConversationForm } from '../types/conversation.schema';
import { conversationSchema } from '../types/conversation.schema';
import type { PresenceMember } from '../types/presence.schema';
import { presenceMemberSchema } from '../types/presence.schema';
import type { Upload } from '../types/upload.schema';
import { uploadSchema } from '../types/upload.schema';
import type { CommunityResource } from '../types/community-resource.schema';
import type { Community, ModeratorPermissions, NewCommunity } from '../types/community.schema';
import { communitySchema } from '../types/community.schema';
import { communityResourceSchema } from '../types/community-resource.schema';
import type { ChatMessage, ChatMessageForm, ChatThread } from '../types/chat-message.schema';
import { chatMessageSchema, chatThreadSchema } from '../types/chat-message.schema';
import type { Page, PageForm, SectionForm } from '../types/page.schema';
import { pageSchema } from '../types/page.schema';
import type { CommunityResourceChanges, NewCommunityResource } from '../types/new-community-resource.schema';
import type { ReactionKind, ReactionSummary, WhoReacted } from '../types/reaction.schema';
import type { AssignableCommunityRole, CommunityInvitation, CommunityInvitationForm, CommunityMember } from '../types/member.schema';
import { communityInvitationSchema, communityMemberSchema } from '../types/member.schema';
import { reactionSummarySchema, whoReactedSchema } from '../types/reaction.schema';

const avatarResponseSchema = z.object({ avatar_url: z.string().nullable() });

/**
 * The item that comments are about: a post (#1175) or a resource of a resources space (#1034)
 */
export type CommentSubject =
  { communityCuid: string; spaceCuid: string; postCuid: string } | { communityCuid: string; spaceCuid: string; resourceCuid: string };

/**
 * The item a reaction targets: a post, a comment of a post, or a comment of a resource
 */
export type ReactionTarget =
  | { communityCuid: string; spaceCuid: string; postCuid: string; commentCuid?: string | undefined }
  | { communityCuid: string; spaceCuid: string; resourceCuid: string; commentCuid: string }
  // A chat message (#1185)
  | { communityCuid: string; spaceCuid: string; messageCuid: string; commentCuid?: undefined }
  // A direct message (#1186)
  | { communityCuid: string; conversationCuid: string; messageCuid: string; commentCuid?: undefined };

const spaceGroupPath = (communityCuid: string, spaceGroupCuid: string, path = COMMUNITY_SPACE_GROUP_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE_GROUP]: spaceGroupCuid });
const spacePath = (communityCuid: string, spaceCuid: string) =>
  apiPath(COMMUNITY_SPACE_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid });
const communityPath = (path: string, communityCuid: string) => apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid });
const memberPath = (communityCuid: string, memberCuid: string) =>
  apiPath(COMMUNITY_MEMBER_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [COMMUNITY_API_PARAM_MEMBER]: memberCuid });
const accessGroupPath = (communityCuid: string, groupCuid: string, path = COMMUNITY_ACCESS_GROUP_API_PATH, memberCuid = '') =>
  apiPath(path, {
    [SPACE_API_PARAM_COMMUNITY]: communityCuid,
    [COMMUNITY_API_PARAM_ACCESS_GROUP]: groupCuid,
    [COMMUNITY_API_PARAM_MEMBER]: memberCuid,
  });
const postsPath = (communityCuid: string, spaceCuid: string, postCuid = '', path = COMMUNITY_SPACE_POSTS_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid, [SPACE_API_PARAM_POST]: postCuid });

const postListSchema = z.array(postSchema);
const pagesPath = (communityCuid: string, spaceCuid: string, pageCuid = '', path = COMMUNITY_SPACE_PAGES_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid, [SPACE_API_PARAM_PAGE]: pageCuid });
const pageListSchema = z.array(pageSchema);
const messagesPath = (communityCuid: string, spaceCuid: string, messageCuid = '', path = COMMUNITY_SPACE_MESSAGES_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid, [SPACE_API_PARAM_MESSAGE]: messageCuid });
const conversationPath = (communityCuid: string, conversationCuid: string, path = COMMUNITY_CONVERSATION_API_PATH, messageCuid = '') =>
  apiPath(path, {
    [SPACE_API_PARAM_COMMUNITY]: communityCuid,
    [CONVERSATION_API_PARAM_CONVERSATION]: conversationCuid,
    [SPACE_API_PARAM_MESSAGE]: messageCuid,
  });
const reactionsPath = (target: ReactionTarget) =>
  'conversationCuid' in target
    ? conversationPath(target.communityCuid, target.conversationCuid, COMMUNITY_CONVERSATION_MESSAGE_REACTIONS_API_PATH, target.messageCuid)
    : 'messageCuid' in target
      ? messagesPath(target.communityCuid, target.spaceCuid, target.messageCuid, COMMUNITY_SPACE_MESSAGE_REACTIONS_API_PATH)
      : target.commentCuid
        ? `${commentsPath(target, target.commentCuid)}/reactions`
        : `${postsPath(target.communityCuid, target.spaceCuid, 'postCuid' in target ? target.postCuid : '', COMMUNITY_SPACE_POST_API_PATH)}/reactions`;
const commentsPath = (subject: CommentSubject, commentCuid?: string) =>
  'resourceCuid' in subject
    ? apiPath(commentCuid ? COMMUNITY_SPACE_RESOURCE_COMMENT_API_PATH : COMMUNITY_SPACE_RESOURCE_COMMENTS_API_PATH, {
        [SPACE_API_PARAM_COMMUNITY]: subject.communityCuid,
        [SPACE_API_PARAM_SPACE]: subject.spaceCuid,
        [SPACE_API_PARAM_RESOURCE]: subject.resourceCuid,
        [SPACE_API_PARAM_COMMENT]: commentCuid ?? '',
      })
    : apiPath(commentCuid ? COMMUNITY_SPACE_POST_COMMENT_API_PATH : COMMUNITY_SPACE_POST_COMMENTS_API_PATH, {
        [SPACE_API_PARAM_COMMUNITY]: subject.communityCuid,
        [SPACE_API_PARAM_SPACE]: subject.spaceCuid,
        [SPACE_API_PARAM_POST]: subject.postCuid,
        [SPACE_API_PARAM_COMMENT]: commentCuid ?? '',
      });
const resourcesPath = (communityCuid: string, spaceCuid: string) =>
  apiPath(COMMUNITY_SPACE_RESOURCES_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid });
const htmlSchema = z.object({ html: z.string() });

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

/**
 * Adds an address to an EmailOctopus list through a public endpoint (the waitlist, the newsletter of the footer #938)
 */
const subscribeToList = async (path: string, input: JoinWaitlistRequest, failureMessage: string): Promise<JoinWaitlistResponse> => {
  const failure: JoinWaitlistResponse = { message: failureMessage, errors: [], type: 'internalError', category: 'technical' };

  try {
    const response = await fetch(path, {
      method: 'post',
      headers: defaultHeaders,
      body: JSON.stringify(input),
    });

    const parsedResponse = joinWaitlistResponseSchema.safeParse(await response.json());

    return parsedResponse.success ? parsedResponse.data : failure;
  } catch {
    return failure;
  }
};

export const knowiiApiClient = {
  waitlist: {
    join: (input: JoinWaitlistRequest): Promise<JoinWaitlistResponse> =>
      subscribeToList(WAITLIST_API_PATH, input, 'Failed to join the waitlist. Please try again later.'),
  },
  newsletter: {
    /**
     * Subscribe to the newsletter (#938). The same input and answer as the waitlist.
     */
    subscribe: (input: JoinWaitlistRequest): Promise<JoinWaitlistResponse> =>
      subscribeToList(NEWSLETTER_API_PATH, input, 'Failed to subscribe to the newsletter. Please try again later.'),
  },
  communities: {
    /**
     * A community with its moderator rights and its number of members (#1135). Members only.
     */
    get: (communityCuid: string): Promise<ApiResult<Community>> =>
      apiRequest('get', communityPath(COMMUNITY_API_PATH, communityCuid), communitySchema, 'Failed to load the community.'),
    /**
     * Change the name, the description or the visibility of a community (owner only, #1129). The slug stays the same.
     */
    update: (communityCuid: string, input: Partial<NewCommunity> & { direct_messages_enabled?: boolean }): Promise<ApiResult<Community>> =>
      apiRequest('patch', communityPath(COMMUNITY_API_PATH, communityCuid), communitySchema, 'Failed to save the community.', input),
    /**
     * Switch some rights of the moderators (owner and admins, #1240). Returns the community.
     */
    updateModeratorPermissions: (communityCuid: string, input: Partial<ModeratorPermissions>): Promise<ApiResult<Community>> =>
      apiRequest(
        'patch',
        communityPath(COMMUNITY_MODERATOR_PERMISSIONS_API_PATH, communityCuid),
        communitySchema,
        'Failed to save the moderator rights.',
        input,
      ),
    create: async (input: CreateCommunityRequest): Promise<CreateCommunityResponse> => {
      try {
        const response = await fetch(COMMUNITY_API_BASE_PATH, {
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const responseAsJson = await response.json();

        const parsedResponse = createCommunityResponseSchema.safeParse(responseAsJson);

        if (parsedResponse.success) {
          return parsedResponse.data;
        }

        return {
          message: responseAsJson.message ? responseAsJson.message : 'Failed to create the community. Please try again later.',
          errors: parsedResponse.error.issues,
          type: responseAsJson.type ? responseAsJson.type : 'internalError',
          category: responseAsJson.category ? responseAsJson.category : 'technical',
        };
      } catch {
        return {
          message: 'Failed to create the community. Please try again later.',
          errors: [],
          type: 'internalError',
          category: 'technical',
        };
      }
    },
    delete: async (input: DeleteCommunityRequest): Promise<boolean> => {
      const requestUrl = COMMUNITY_API_PATH.replace(COMMUNITY_API_PATH_PARAM_COMMUNITY, input.community.cuid);

      try {
        const response = await fetch(requestUrl, {
          method: 'delete',
          headers: defaultHeaders,
        });

        return response.status === HttpStatus.NO_CONTENT;
      } catch {
        return false;
      }
    },
    spaceGroups: {
      list: (communityCuid: string): Promise<ApiResult<SpaceGroup[]>> =>
        apiRequest(
          'get',
          communityPath(COMMUNITY_SPACE_GROUPS_API_PATH, communityCuid),
          z.array(spaceGroupSchema),
          'Failed to load the spaces.',
        ),
      create: (communityCuid: string, input: SpaceGroupForm): Promise<ApiResult<SpaceGroup>> =>
        apiRequest(
          'post',
          communityPath(COMMUNITY_SPACE_GROUPS_API_PATH, communityCuid),
          spaceGroupSchema,
          'Failed to create the space group.',
          input,
        ),
      update: (communityCuid: string, spaceGroupCuid: string, input: SpaceGroupForm): Promise<ApiResult<SpaceGroup>> =>
        apiRequest('patch', spaceGroupPath(communityCuid, spaceGroupCuid), spaceGroupSchema, 'Failed to rename the space group.', input),
      delete: (communityCuid: string, spaceGroupCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest('delete', spaceGroupPath(communityCuid, spaceGroupCuid), null, 'Failed to delete the space group.'),
      reorder: (communityCuid: string, spaceGroupCuids: string[]): Promise<ApiResult<SpaceGroup[]>> =>
        apiRequest(
          'post',
          communityPath(COMMUNITY_SPACE_GROUPS_REORDER_API_PATH, communityCuid),
          z.array(spaceGroupSchema),
          'Failed to reorder the space groups.',
          { space_group_cuids: spaceGroupCuids },
        ),
    },
    /**
     * The members of the community who are online now and show it (#1187, ADR 0027)
     */
    onlineMembers: (communityCuid: string): Promise<ApiResult<PresenceMember[]>> =>
      apiRequest(
        'get',
        communityPath(COMMUNITY_ONLINE_MEMBERS_API_PATH, communityCuid),
        z.array(presenceMemberSchema),
        'Failed to load who is online.',
      ),
    spaces: {
      /**
       * The members who are in the chat space now and show it (#1187, ADR 0027)
       */
      onlineMembers: (communityCuid: string, spaceCuid: string): Promise<ApiResult<PresenceMember[]>> =>
        apiRequest(
          'get',
          apiPath(COMMUNITY_SPACE_ONLINE_MEMBERS_API_PATH, {
            [SPACE_API_PARAM_COMMUNITY]: communityCuid,
            [SPACE_API_PARAM_SPACE]: spaceCuid,
          }),
          z.array(presenceMemberSchema),
          'Failed to load who is in the space.',
        ),
      create: (communityCuid: string, input: SpaceCreateInput): Promise<ApiResult<Space>> =>
        apiRequest('post', communityPath(COMMUNITY_SPACES_API_PATH, communityCuid), spaceSchema, 'Failed to create the space.', input),
      update: (communityCuid: string, spaceCuid: string, input: Partial<Omit<SpaceForm, 'type'>>): Promise<ApiResult<Space>> =>
        apiRequest('patch', spacePath(communityCuid, spaceCuid), spaceSchema, 'Failed to update the space.', input),
      delete: (communityCuid: string, spaceCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest('delete', spacePath(communityCuid, spaceCuid), null, 'Failed to delete the space.'),
      reorder: (communityCuid: string, spaceGroupCuid: string, spaceCuids: string[]): Promise<ApiResult<Space[]>> =>
        apiRequest(
          'post',
          spaceGroupPath(communityCuid, spaceGroupCuid, COMMUNITY_SPACE_GROUP_SPACES_REORDER_API_PATH),
          z.array(spaceSchema),
          'Failed to reorder the spaces.',
          { space_cuids: spaceCuids },
        ),
    },
    posts: {
      /**
       * A page of posts, with the pagination metadata of the guide (ADR 0011)
       */
      /**
       * One post, with the reactions of the current member (#1180: live updates load the changed post)
       */
      get: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<Post>> =>
        apiRequest(
          'get',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_API_PATH),
          postSchema,
          'Failed to load the post.',
        ),
      list: async (communityCuid: string, spaceCuid: string, limit: number, offset: number): Promise<ApiResult<Paginated<Post>>> => {
        const url = `${postsPath(communityCuid, spaceCuid)}?limit=${limit}&offset=${offset}`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const json: unknown = await response.json();
          const parsed = z.object({ data: postListSchema, metadata: z.object({ pagination: paginationSchema }) }).safeParse(json);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message: 'Failed to load the posts.', errors: {} };
          }
          return { ok: true, message: '', data: { items: parsed.data.data, pagination: parsed.data.metadata.pagination } };
        } catch {
          return { ok: false, status: 0, message: 'Failed to load the posts.', errors: {} };
        }
      },
      create: (communityCuid: string, spaceCuid: string, input: PostForm): Promise<ApiResult<Post>> =>
        apiRequest('post', postsPath(communityCuid, spaceCuid), postSchema, 'Failed to publish the post.', input),
      update: (communityCuid: string, spaceCuid: string, postCuid: string, input: Partial<PostForm>): Promise<ApiResult<Post>> =>
        apiRequest(
          'patch',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_API_PATH),
          postSchema,
          'Failed to save the post.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_API_PATH),
          null,
          'Failed to delete the post.',
        ),
      pin: (communityCuid: string, spaceCuid: string, postCuid: string, pinned: boolean): Promise<ApiResult<Post>> =>
        apiRequest(
          'post',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_PIN_API_PATH),
          postSchema,
          pinned ? 'Failed to pin the post.' : 'Failed to unpin the post.',
          { pinned },
        ),
    },
    /**
     * The messages of chat spaces and their threads (#1185)
     */
    chat: {
      /**
       * The newest first-level messages, oldest first; with `before` (a message cuid), the older ones before it
       */
      list: async (
        communityCuid: string,
        spaceCuid: string,
        before?: string,
      ): Promise<ApiResult<{ messages: ChatMessage[]; hasMore: boolean }>> => {
        const url = `${messagesPath(communityCuid, spaceCuid)}${before ? `?before=${encodeURIComponent(before)}` : ''}`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const json: unknown = await response.json();
          const parsed = z.object({ data: z.array(chatMessageSchema), metadata: z.object({ has_more: z.boolean() }) }).safeParse(json);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message: 'Failed to load the messages.', errors: {} };
          }
          return { ok: true, message: '', data: { messages: parsed.data.data, hasMore: parsed.data.metadata.has_more } };
        } catch {
          return { ok: false, status: 0, message: 'Failed to load the messages.', errors: {} };
        }
      },
      get: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ChatMessage>> =>
        apiRequest(
          'get',
          messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_API_PATH),
          chatMessageSchema,
          'Failed to load the message.',
        ),
      thread: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ChatThread>> =>
        apiRequest(
          'get',
          messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_THREAD_API_PATH),
          chatThreadSchema,
          'Failed to load the thread.',
        ),
      create: (
        communityCuid: string,
        spaceCuid: string,
        input: ChatMessageForm & { parent_cuid?: string },
      ): Promise<ApiResult<ChatMessage>> =>
        apiRequest('post', messagesPath(communityCuid, spaceCuid), chatMessageSchema, 'Failed to send the message.', input),
      update: (communityCuid: string, spaceCuid: string, messageCuid: string, input: ChatMessageForm): Promise<ApiResult<ChatMessage>> =>
        apiRequest(
          'patch',
          messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_API_PATH),
          chatMessageSchema,
          'Failed to save the message.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_API_PATH),
          null,
          'Failed to delete the message.',
        ),
      markAsRead: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'post',
          messagesPath(communityCuid, spaceCuid, '', COMMUNITY_SPACE_MESSAGES_READ_API_PATH),
          null,
          'Failed to save what you read.',
          {
            message_cuid: messageCuid,
          },
        ),
    },
    /**
     * Direct messages (#1186, ADR 0028): the conversations of the current member and their messages
     */
    conversations: {
      list: async (
        communityCuid: string,
        limit = 50,
        offset = 0,
      ): Promise<ApiResult<{ conversations: Conversation[]; unreadTotal: number }>> => {
        const url = `${conversationPath(communityCuid, '', COMMUNITY_CONVERSATIONS_API_PATH)}?limit=${limit}&offset=${offset}`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const json: unknown = await response.json();
          const parsed = z
            .object({ data: z.array(conversationSchema), metadata: z.object({ unread_total: z.number().int() }) })
            .safeParse(json);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message: 'Failed to load your conversations.', errors: {} };
          }
          return { ok: true, message: '', data: { conversations: parsed.data.data, unreadTotal: parsed.data.metadata.unread_total } };
        } catch {
          return { ok: false, status: 0, message: 'Failed to load your conversations.', errors: {} };
        }
      },
      start: (communityCuid: string, input: NewConversationForm): Promise<ApiResult<Conversation>> =>
        apiRequest(
          'post',
          conversationPath(communityCuid, '', COMMUNITY_CONVERSATIONS_API_PATH),
          conversationSchema,
          'Failed to send the message.',
          input,
        ),
      get: (communityCuid: string, conversationCuid: string): Promise<ApiResult<Conversation>> =>
        apiRequest('get', conversationPath(communityCuid, conversationCuid), conversationSchema, 'Failed to load the conversation.'),
      messages: async (
        communityCuid: string,
        conversationCuid: string,
        before?: string,
      ): Promise<ApiResult<{ messages: ChatMessage[]; hasMore: boolean }>> => {
        const url = `${conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_MESSAGES_API_PATH)}${
          before ? `?before=${encodeURIComponent(before)}` : ''
        }`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const json: unknown = await response.json();
          const parsed = z.object({ data: z.array(chatMessageSchema), metadata: z.object({ has_more: z.boolean() }) }).safeParse(json);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message: 'Failed to load the messages.', errors: {} };
          }
          return { ok: true, message: '', data: { messages: parsed.data.data, hasMore: parsed.data.metadata.has_more } };
        } catch {
          return { ok: false, status: 0, message: 'Failed to load the messages.', errors: {} };
        }
      },
      message: (communityCuid: string, conversationCuid: string, messageCuid: string): Promise<ApiResult<ChatMessage>> =>
        apiRequest(
          'get',
          conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_MESSAGE_API_PATH, messageCuid),
          chatMessageSchema,
          'Failed to load the message.',
        ),
      send: (communityCuid: string, conversationCuid: string, input: ChatMessageForm): Promise<ApiResult<ChatMessage>> =>
        apiRequest(
          'post',
          conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_MESSAGES_API_PATH),
          chatMessageSchema,
          'Failed to send the message.',
          input,
        ),
      update: (
        communityCuid: string,
        conversationCuid: string,
        messageCuid: string,
        input: ChatMessageForm,
      ): Promise<ApiResult<ChatMessage>> =>
        apiRequest(
          'patch',
          conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_MESSAGE_API_PATH, messageCuid),
          chatMessageSchema,
          'Failed to save the message.',
          input,
        ),
      delete: (communityCuid: string, conversationCuid: string, messageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_MESSAGE_API_PATH, messageCuid),
          null,
          'Failed to delete the message.',
        ),
      markAsRead: (communityCuid: string, conversationCuid: string, messageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'post',
          conversationPath(communityCuid, conversationCuid, COMMUNITY_CONVERSATION_READ_API_PATH),
          null,
          'Failed to save what you read.',
          { message_cuid: messageCuid },
        ),
    },
    /**
     * The pages of page spaces (#1179): the owner, the admins and the moderators manage them
     */
    pages: {
      list: (communityCuid: string, spaceCuid: string): Promise<ApiResult<Page[]>> =>
        apiRequest('get', pagesPath(communityCuid, spaceCuid), pageListSchema, 'Failed to load the pages.'),
      get: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<Page>> =>
        apiRequest(
          'get',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_API_PATH),
          pageSchema,
          'Failed to load the page.',
        ),
      create: (
        communityCuid: string,
        spaceCuid: string,
        input: (PageForm & { video_url?: string; kind?: 'page' }) | (SectionForm & { kind: 'section' }),
      ): Promise<ApiResult<Page>> =>
        apiRequest('post', pagesPath(communityCuid, spaceCuid), pageSchema, 'Failed to create the page.', input),
      update: (
        communityCuid: string,
        spaceCuid: string,
        pageCuid: string,
        input: Partial<PageForm & { video_url: string }>,
      ): Promise<ApiResult<Page>> =>
        apiRequest(
          'patch',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_API_PATH),
          pageSchema,
          'Failed to save the page.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_API_PATH),
          null,
          'Failed to delete the page.',
        ),
      reorder: (communityCuid: string, spaceCuid: string, pageCuids: string[]): Promise<ApiResult<Page[]>> =>
        apiRequest(
          'post',
          pagesPath(communityCuid, spaceCuid, '', COMMUNITY_SPACE_PAGES_ORDER_API_PATH),
          pageListSchema,
          'Failed to move the page.',
          { pages: pageCuids },
        ),
    },
    comments: {
      list: (subject: CommentSubject): Promise<ApiResult<Comment[]>> =>
        apiRequest('get', commentsPath(subject), z.array(commentSchema), 'Failed to load the comments.'),
      create: (subject: CommentSubject, input: CommentForm & { parent_cuid?: string | null }): Promise<ApiResult<Comment>> =>
        apiRequest('post', commentsPath(subject), commentSchema, 'Failed to publish the comment.', input),
      update: (subject: CommentSubject, commentCuid: string, input: CommentForm): Promise<ApiResult<Comment>> =>
        apiRequest('patch', commentsPath(subject, commentCuid), commentSchema, 'Failed to save the comment.', input),
      delete: (subject: CommentSubject, commentCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest('delete', commentsPath(subject, commentCuid), null, 'Failed to delete the comment.'),
    },
    uploads: {
      /**
       * Upload an image with its alt text (multipart). The server re-encodes it and scales it down.
       */
      image: async (communityCuid: string, file: File, alt: string): Promise<ApiResult<Upload>> => {
        const failure = 'Failed to upload the image.';
        const body = new FormData();
        body.append('file', file);
        body.append('alt', alt);
        try {
          // No Content-Type header: the browser sets the multipart boundary
          const response = await fetch(communityPath(COMMUNITY_UPLOADS_API_PATH, communityCuid), {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body,
          });
          const json: unknown = await response.json();
          const envelope = typeof json === 'object' && json !== null ? (json as Record<string, unknown>) : {};
          const message = typeof envelope['message'] === 'string' && envelope['message'] !== '' ? envelope['message'] : failure;
          const parsed = uploadSchema.safeParse(envelope['data']);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message, errors: {} };
          }
          return { ok: true, message, data: parsed.data };
        } catch {
          return { ok: false, status: 0, message: failure, errors: {} };
        }
      },
    },
    invitations: {
      /**
       * The pending invitations, newest first (#1242).
       */
      list: (communityCuid: string): Promise<ApiResult<CommunityInvitation[]>> =>
        apiRequest(
          'get',
          communityPath(COMMUNITY_INVITATIONS_API_PATH, communityCuid),
          z.array(communityInvitationSchema),
          'Failed to load the invitations.',
        ),
      /**
       * Invite an email address with a role, and send the invitation email (#1242).
       */
      create: (communityCuid: string, input: CommunityInvitationForm): Promise<ApiResult<CommunityInvitation>> =>
        apiRequest(
          'post',
          communityPath(COMMUNITY_INVITATIONS_API_PATH, communityCuid),
          communityInvitationSchema,
          'Failed to send the invitation.',
          input,
        ),
      /**
       * Cancel a pending invitation (#1242).
       */
      cancel: (communityCuid: string, invitationCuid: string): Promise<ApiResult<void>> =>
        apiRequest(
          'delete',
          apiPath(COMMUNITY_INVITATION_API_PATH, {
            [SPACE_API_PARAM_COMMUNITY]: communityCuid,
            [COMMUNITY_API_PARAM_INVITATION]: invitationCuid,
          }),
          null,
          'Failed to cancel the invitation.',
        ),
    },
    members: {
      /**
       * A page of the members with their role (#1242): owner first, then admins, moderators and members. An optional
       * query keeps the members whose username or a word of their name starts with it.
       */
      list: async (communityCuid: string, limit: number, offset: number, query = ''): Promise<ApiResult<Paginated<CommunityMember>>> => {
        const search = query === '' ? '' : `&query=${encodeURIComponent(query)}`;
        const url = `${communityPath(COMMUNITY_MEMBERS_API_PATH, communityCuid)}?limit=${limit}&offset=${offset}${search}`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' } });
          const json: unknown = await response.json();
          const parsed = z
            .object({ data: z.array(communityMemberSchema), metadata: z.object({ pagination: paginationSchema }) })
            .safeParse(json);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message: 'Failed to load the members.', errors: {} };
          }
          return { ok: true, message: '', data: { items: parsed.data.data, pagination: parsed.data.metadata.pagination } };
        } catch {
          return { ok: false, status: 0, message: 'Failed to load the members.', errors: {} };
        }
      },
      /**
       * Change the role of a member, within the role hierarchy (#1242). Returns the member with the new role.
       */
      updateRole: (communityCuid: string, memberCuid: string, role: AssignableCommunityRole): Promise<ApiResult<CommunityMember>> =>
        apiRequest('patch', memberPath(communityCuid, memberCuid), communityMemberSchema, 'Failed to change the role.', { role }),
      /**
       * Remove a member from the community, within the role hierarchy (#1242).
       */
      remove: (communityCuid: string, memberCuid: string): Promise<ApiResult<void>> =>
        apiRequest('delete', memberPath(communityCuid, memberCuid), null, 'Failed to remove the member.'),
      /**
       * Members whose username or name starts with the query (mention autocomplete). Returns an empty list on failure.
       */
      search: async (communityCuid: string, query: string, limit: number, signal?: AbortSignal): Promise<Author[]> => {
        const url = `${communityPath(COMMUNITY_MEMBERS_API_PATH, communityCuid)}?query=${encodeURIComponent(query)}&limit=${limit}`;
        try {
          const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: signal ?? null });
          const parsed = z.object({ data: z.array(authorSchema) }).safeParse(await response.json());
          return response.ok && parsed.success ? parsed.data.data : [];
        } catch {
          return [];
        }
      },
    },
    accessGroups: {
      /**
       * The access groups of the community, by name, with their counts (#1241).
       */
      list: (communityCuid: string): Promise<ApiResult<AccessGroup[]>> =>
        apiRequest(
          'get',
          communityPath(COMMUNITY_ACCESS_GROUPS_API_PATH, communityCuid),
          z.array(accessGroupSchema),
          'Failed to load the access groups.',
        ),
      create: (communityCuid: string, input: AccessGroupForm): Promise<ApiResult<AccessGroup>> =>
        apiRequest(
          'post',
          communityPath(COMMUNITY_ACCESS_GROUPS_API_PATH, communityCuid),
          accessGroupSchema,
          'Failed to create the access group.',
          input,
        ),
      update: (communityCuid: string, groupCuid: string, input: Partial<AccessGroupForm>): Promise<ApiResult<AccessGroup>> =>
        apiRequest('patch', accessGroupPath(communityCuid, groupCuid), accessGroupSchema, 'Failed to save the access group.', input),
      delete: (communityCuid: string, groupCuid: string): Promise<ApiResult<void>> =>
        apiRequest('delete', accessGroupPath(communityCuid, groupCuid), null, 'Failed to delete the access group.'),
      members: {
        /**
         * A page of the members of a group, by name (#1241).
         */
        list: async (communityCuid: string, groupCuid: string, limit: number, offset: number): Promise<ApiResult<Paginated<Author>>> => {
          const url = `${accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH)}?limit=${limit}&offset=${offset}`;
          try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const parsed = z
              .object({ data: z.array(authorSchema), metadata: z.object({ pagination: paginationSchema }) })
              .safeParse(await response.json());
            if (!response.ok || !parsed.success) {
              return { ok: false, status: response.status, message: 'Failed to load the members of the group.', errors: {} };
            }
            return { ok: true, message: '', data: { items: parsed.data.data, pagination: parsed.data.metadata.pagination } };
          } catch {
            return { ok: false, status: 0, message: 'Failed to load the members of the group.', errors: {} };
          }
        },
        /**
         * Add a member of the community, named by cuid, username or email (#1241). Adding twice changes nothing.
         */
        add: (communityCuid: string, groupCuid: string, input: AccessGroupMemberInput): Promise<ApiResult<Author>> =>
          apiRequest(
            'post',
            accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH),
            authorSchema,
            'Failed to add the member.',
            input,
          ),
        remove: (communityCuid: string, groupCuid: string, memberCuid: string): Promise<ApiResult<void>> =>
          apiRequest(
            'delete',
            accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_MEMBER_API_PATH, memberCuid),
            null,
            'Failed to remove the member.',
          ),
      },
    },
    reactions: {
      /**
       * Add the reaction of the member, or remove it. Returns the new summary.
       */
      toggle: (target: ReactionTarget, kind: ReactionKind): Promise<ApiResult<ReactionSummary>> =>
        apiRequest('post', reactionsPath(target), reactionSummarySchema, 'Failed to save the reaction.', { kind }),
      whoReacted: (target: ReactionTarget): Promise<ApiResult<WhoReacted>> =>
        apiRequest('get', reactionsPath(target), whoReactedSchema, 'Failed to load the reactions.'),
    },
  },
  resources: {
    /**
     * A page of resources of a resources space, newest first, with the pagination metadata of the guide (ADR 0011)
     */
    list: async (
      communityCuid: string,
      spaceCuid: string,
      limit: number,
      offset: number,
    ): Promise<ApiResult<Paginated<CommunityResource>>> => {
      const url = `${resourcesPath(communityCuid, spaceCuid)}?limit=${limit}&offset=${offset}`;
      try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const json: unknown = await response.json();
        const parsed = z
          .object({ data: z.array(communityResourceSchema), metadata: z.object({ pagination: paginationSchema }) })
          .safeParse(json);
        if (!response.ok || !parsed.success) {
          return { ok: false, status: response.status, message: 'Failed to load the resources.', errors: {} };
        }
        return { ok: true, message: '', data: { items: parsed.data.data, pagination: parsed.data.metadata.pagination } };
      } catch {
        return { ok: false, status: 0, message: 'Failed to load the resources.', errors: {} };
      }
    },
    /**
     * One resource, with its captured Markdown (ADR 0018)
     */
    get: (communityCuid: string, spaceCuid: string, resourceCuid: string): Promise<ApiResult<CommunityResource>> =>
      apiRequest(
        'get',
        apiPath(COMMUNITY_SPACE_RESOURCE_API_PATH, {
          [SPACE_API_PARAM_COMMUNITY]: communityCuid,
          [SPACE_API_PARAM_SPACE]: spaceCuid,
          [SPACE_API_PARAM_RESOURCE]: resourceCuid,
        }),
        communityResourceSchema,
        'Failed to load the resource.',
      ),
    /**
     * Add a link to a resources space (ADR 0015). The answer comes at once, "pending": a queued job captures the page
     * (ADR 0018)
     */
    create: (communityCuid: string, spaceCuid: string, input: NewCommunityResource): Promise<ApiResult<CommunityResource>> =>
      apiRequest('post', resourcesPath(communityCuid, spaceCuid), communityResourceSchema, 'Failed to add the resource.', input),
    /**
     * Change the type or the level of a resource (#1246): the curator, or a member who manages the spaces
     */
    update: (
      communityCuid: string,
      spaceCuid: string,
      resourceCuid: string,
      input: CommunityResourceChanges,
    ): Promise<ApiResult<CommunityResource>> =>
      apiRequest(
        'patch',
        apiPath(COMMUNITY_SPACE_RESOURCE_API_PATH, {
          [SPACE_API_PARAM_COMMUNITY]: communityCuid,
          [SPACE_API_PARAM_SPACE]: spaceCuid,
          [SPACE_API_PARAM_RESOURCE]: resourceCuid,
        }),
        communityResourceSchema,
        'Failed to save the resource.',
        input,
      ),
    /**
     * Start the capture of a resource again (ADR 0018): the curator, or a member who manages the spaces
     */
    capture: (communityCuid: string, spaceCuid: string, resourceCuid: string): Promise<ApiResult<CommunityResource>> =>
      apiRequest(
        'post',
        apiPath(COMMUNITY_SPACE_RESOURCE_CAPTURE_API_PATH, {
          [SPACE_API_PARAM_COMMUNITY]: communityCuid,
          [SPACE_API_PARAM_SPACE]: spaceCuid,
          [SPACE_API_PARAM_RESOURCE]: resourceCuid,
        }),
        communityResourceSchema,
        'Failed to start the capture.',
      ),
  },
  users: {
    /**
     * Replace the avatar of the current user (multipart, #109). Returns the URL of the new avatar.
     */
    updateAvatar: async (file: File): Promise<ApiResult<string | null>> => {
      const failure = 'Failed to save the picture.';
      const body = new FormData();
      body.append('file', file);
      try {
        // No Content-Type header: the browser sets the multipart boundary
        const response = await fetch(USER_AVATAR_API_PATH, { method: 'POST', headers: { Accept: 'application/json' }, body });
        const json: unknown = await response.json();
        const envelope = typeof json === 'object' && json !== null ? (json as Record<string, unknown>) : {};
        const message = typeof envelope['message'] === 'string' && envelope['message'] !== '' ? envelope['message'] : failure;
        const parsed = avatarResponseSchema.safeParse(envelope['data']);
        if (!response.ok || !parsed.success) {
          return { ok: false, status: response.status, message, errors: fieldErrors(envelope['errors']) };
        }
        return { ok: true, message, data: parsed.data.avatar_url };
      } catch {
        return { ok: false, status: 0, message: failure, errors: {} };
      }
    },
    /**
     * Remove the avatar of the current user (#109): the initials show again.
     */
    deleteAvatar: (): Promise<ApiResult<unknown>> => apiRequest('delete', USER_AVATAR_API_PATH, null, 'Failed to remove the picture.'),
    /**
     * Show or hide when the current user is online (#1187, ADR 0027)
     */
    /**
     * The members the current user blocked (#1186): they cannot write to each other
     */
    blocks: {
      list: (): Promise<ApiResult<Author[]>> =>
        apiRequest('get', USER_BLOCKS_API_PATH, z.array(authorSchema), 'Failed to load the blocked members.'),
      block: (userCuid: string): Promise<ApiResult<unknown>> =>
        apiRequest('put', `${USER_BLOCKS_API_PATH}/${encodeURIComponent(userCuid)}`, null, 'Failed to block the member.'),
      unblock: (userCuid: string): Promise<ApiResult<unknown>> =>
        apiRequest('delete', `${USER_BLOCKS_API_PATH}/${encodeURIComponent(userCuid)}`, null, 'Failed to unblock the member.'),
    },
    updateOnlineStatus: (show: boolean): Promise<ApiResult<unknown>> =>
      apiRequest('patch', USER_ONLINE_STATUS_API_PATH, null, 'Failed to change your online status.', { show_online_status: show }),
    isUsernameAvailable: async (input: IsUsernameAvailableRequest, signal?: AbortSignal): Promise<IsUsernameAvailableResponse> => {
      try {
        const response = await fetch(USERS_API_IS_USERNAME_AVAILABLE_PATH, {
          signal: signal ?? null,
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const responseAsJson = await response.json();

        return isUsernameAvailableResponseSchema.parse(responseAsJson);
      } catch {
        return {
          message: 'Failed to check if the username is available. Please try again later.',
          errors: [],
          type: 'internalError',
          category: 'technical',
        };
      }
    },
  },
  markdown: {
    /**
     * Render Markdown with the server renderer, so the preview equals the published content
     */
    preview: async (markdown: string, signal?: AbortSignal, communityCuid?: string): Promise<string | null> => {
      try {
        const response = await fetch(MARKDOWN_PREVIEW_API_PATH, {
          method: 'post',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ markdown, community_cuid: communityCuid ?? null }),
          signal: signal ?? null,
        });
        const parsed = z.object({ data: htmlSchema }).safeParse(await response.json());
        return response.ok && parsed.success ? parsed.data.data.html : null;
      } catch {
        return null;
      }
    },
  },
  utils: {
    ping: async (): Promise<PingResponse> => {
      try {
        const response = await fetch(PING_API_PATH, {
          method: 'get',
          headers: defaultHeaders,
        });

        const responseAsJson = await response.json();

        return pingResponseSchema.parse(responseAsJson);
      } catch {
        return {
          message: 'Failed to ping the server. Please try again later.',
          errors: [],
          type: 'internalError',
          category: 'technical',
        };
      }
    },
  },
};
