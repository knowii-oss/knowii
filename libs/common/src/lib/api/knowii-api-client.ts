import {
  COMMUNITY_API_BASE_PATH,
  COMMUNITY_API_IS_NAME_AVAILABLE_PATH,
  COMMUNITY_OWNERSHIP_API_PATH,
  USER_AVATAR_API_PATH,
  USER_BLOCKS_API_PATH,
  USER_FREQUENT_EMOJI_API_PATH,
  CONVERSATION_API_PARAM_CONVERSATION,
  COMMUNITY_CONVERSATIONS_API_PATH,
  COMMUNITY_CONVERSATION_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGES_API_PATH,
  COMMUNITY_CONVERSATION_READ_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGE_API_PATH,
  COMMUNITY_CONVERSATION_MESSAGE_REACTIONS_API_PATH,
  USER_IDENTITIES_API_PATH,
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
  COMMUNITY_MEMBERSHIP_API_PATH,
  COMMUNITY_JOIN_POLICY_API_PATH,
  COMMUNITY_INDEXING_API_PATH,
  COMMUNITY_JOIN_BLOCKS_API_PATH,
  COMMUNITY_JOIN_BLOCK_API_PATH,
  COMMUNITY_API_PARAM_MEMBER,
  COMMUNITY_INVITATIONS_API_PATH,
  COMMUNITY_INVITATION_API_PATH,
  COMMUNITY_API_PARAM_INVITATION,
  COMMUNITY_MODERATOR_PERMISSIONS_API_PATH,
  COMMUNITY_ACCESS_GROUPS_API_PATH,
  COMMUNITY_ACCESS_GROUP_API_PATH,
  COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH,
  COMMUNITY_ACCESS_GROUP_RULES_API_PATH,
  COMMUNITY_ACCESS_GROUP_SPACE_GROUPS_API_PATH,
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
  COMMUNITY_SPACE_PAGE_PROGRESS_API_PATH,
  COMMUNITY_SPACE_PAGE_QUIZ_API_PATH,
  COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS_API_PATH,
  COMMUNITY_SPACE_PROGRESS_API_PATH,
  COMMUNITY_SPACE_MATERIALS_API_PATH,
  COMMUNITY_SPACE_MATERIALS_ORDER_API_PATH,
  COMMUNITY_SPACE_MATERIAL_API_PATH,
  COMMUNITY_SPACE_MATERIAL_DOWNLOAD_API_PATH,
  SPACE_API_PARAM_MATERIAL,
  COMMUNITY_SPACE_POST_API_PATH,
  COMMUNITY_SPACE_POST_COMMENT_API_PATH,
  COMMUNITY_SPACE_POST_COMMENTS_API_PATH,
  SPACE_API_PARAM_COMMENT,
  COMMUNITY_SPACE_POST_PIN_API_PATH,
  COMMUNITY_SPACE_POSTS_API_PATH,
  COMMUNITY_EVENTS_API_PATH,
  COMMUNITY_SPACE_EVENTS_API_PATH,
  COMMUNITY_SPACE_EVENT_API_PATH,
  COMMUNITY_SPACE_EVENT_STATE_API_PATH,
  COMMUNITY_SPACE_EVENT_DUPLICATE_API_PATH,
  COMMUNITY_SPACE_EVENT_ANSWER_API_PATH,
  COMMUNITY_SPACE_EVENT_ANSWERS_API_PATH,
  EVENT_ANSWERS_PER_PAGE,
  SPACE_API_PARAM_EVENT,
  EVENTS_PER_PAGE,
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
  NOTIFICATIONS_PER_PAGE,
  NOTIFICATION_API_PARAM_NOTIFICATION,
  NOTIFICATION_READ_API_PATH,
  NOTIFICATION_UNREAD_API_PATH,
  NOTIFICATIONS_API_PATH,
  NOTIFICATIONS_COUNT_API_PATH,
  NOTIFICATIONS_SEEN_API_PATH,
  NOTIFICATIONS_CAUGHT_UP_API_PATH,
  COMMUNITY_CATCH_UP_API_PATH,
  COMMUNITY_SPACE_VISIT_API_PATH,
  USER_TIME_ZONE_API_PATH,
  USER_NOTIFICATION_SETTINGS_API_PATH,
  COMMUNITY_NOTIFICATION_LEVELS_API_PATH,
  COMMUNITY_SPACE_NOTIFICATION_LEVEL_API_PATH,
  COMMUNITY_SPACE_POST_FOLLOW_API_PATH,
  COMMUNITY_SPACE_POST_MUTE_API_PATH,
  COMMUNITY_SPACE_MESSAGE_FOLLOW_API_PATH,
  COMMUNITY_SPACE_MESSAGE_MUTE_API_PATH,
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
import type {
  AccessGroup,
  AccessGroupForm,
  AccessGroupMember,
  AccessGroupMemberInput,
  AccessGroupSpaceGroup,
} from '../types/access-group.schema';
import { accessGroupMemberSchema, accessGroupSchema, accessGroupSpaceGroupSchema } from '../types/access-group.schema';
import { HttpStatus } from '../types/http-status.intf';
import type { ApiResult } from './api-result';
import { apiPageRequest, apiPageRequestWith, apiPath, apiRequest, fieldErrors } from './api-result';
import type { EventRequest, EventScope, EventUpdateRequest } from './communities/event-request.schema';
import type { EventState, SpaceEvent } from '../types/event.schema';
import { eventSchema } from '../types/event.schema';
import type { EventAnswer, EventAnswerCounts, EventAnswerMember, EventAnswerValue } from '../types/event-answer.schema';
import { eventAnswerCountsSchema, eventAnswerMemberSchema, eventAnswerSchema } from '../types/event-answer.schema';
import type { Space, SpaceCreateInput, SpaceForm, SpaceGroup, SpaceGroupForm } from '../types/space.schema';
import { spaceGroupSchema, spaceSchema } from '../types/space.schema';
import type { Author, Paginated, Pagination, Post, PostCreateInput, PostForm } from '../types/post.schema';
import { authorSchema, paginationSchema, postSchema } from '../types/post.schema';
import type { Comment, CommentForm } from '../types/comment.schema';
import { commentSchema } from '../types/comment.schema';
import type { Conversation, NewConversationForm } from '../types/conversation.schema';
import { conversationSchema } from '../types/conversation.schema';
import type { CatchUp, Notification } from '../types/notification.schema';
import { catchUpSchema, notificationSchema } from '../types/notification.schema';
import type { CommunitySpaceLevel, SpaceLevel, SpaceNotificationLevel, ThreadFollow } from '../types/notification-follow.schema';
import { communitySpaceLevelSchema, spaceLevelSchema, threadFollowSchema } from '../types/notification-follow.schema';
import type { PresenceMember } from '../types/presence.schema';
import { presenceMemberSchema } from '../types/presence.schema';
import type { Upload } from '../types/upload.schema';
import { uploadSchema } from '../types/upload.schema';
import type { CommunityResource } from '../types/community-resource.schema';
import type { Community, CommunityJoinPolicy, ModeratorPermissions, NewCommunity } from '../types/community.schema';
import { communitySchema } from '../types/community.schema';
import { communityResourceSchema } from '../types/community-resource.schema';
import type { ChatMessage, ChatMessageForm, ChatThread } from '../types/chat-message.schema';
import { chatMessageSchema, chatThreadSchema } from '../types/chat-message.schema';
import type { Page, PageForm, SectionForm } from '../types/page.schema';
import type { CourseProgress } from '../types/page.schema';
import { courseProgressSchema, pageSchema } from '../types/page.schema';
import type { CourseMaterial } from '../types/course-material.schema';
import { courseMaterialSchema } from '../types/course-material.schema';
import type { LessonQuiz, QuizAnswer, QuizAttempt, QuizForm } from '../types/quiz.schema';
import { lessonQuizSchema, quizAttemptSchema } from '../types/quiz.schema';
import type { CommunityResourceChanges, NewCommunityResource } from '../types/new-community-resource.schema';
import type { ReactionSummary, WhoReacted } from '../types/reaction.schema';
import type {
  AssignableCommunityRole,
  CommunityInvitation,
  CommunityInvitationForm,
  CommunityJoinBlock,
  CommunityMember,
} from '../types/member.schema';
import { communityInvitationSchema, communityJoinBlockSchema, communityMemberSchema } from '../types/member.schema';
import { reactionEmojiSchema, reactionSummarySchema, whoReactedSchema } from '../types/reaction.schema';

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
const materialsPath = (communityCuid: string, spaceCuid: string, path = COMMUNITY_SPACE_MATERIALS_API_PATH, materialCuid = '') =>
  apiPath(path, {
    [SPACE_API_PARAM_COMMUNITY]: communityCuid,
    [SPACE_API_PARAM_SPACE]: spaceCuid,
    [SPACE_API_PARAM_MATERIAL]: materialCuid,
  });
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

const eventsPath = (communityCuid: string, spaceCuid: string, eventCuid = '', path = COMMUNITY_SPACE_EVENTS_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid, [SPACE_API_PARAM_EVENT]: eventCuid });

const FOLLOW_FAILURE = 'Failed to save what you follow.';
const spaceLevelPath = (communityCuid: string, spaceCuid: string) =>
  apiPath(COMMUNITY_SPACE_NOTIFICATION_LEVEL_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid });

/**
 * A page of the notifications of one level: 1 is "Needs you" (with `older`), 2 is "Following"
 */
async function listNotifications(
  input: { limit?: number; offset?: number; older?: boolean },
  level: 1 | 2,
  signal?: AbortSignal,
): Promise<ApiResult<{ items: Notification[]; pagination: Pagination }>> {
  const query = new URLSearchParams({ limit: String(input.limit ?? NOTIFICATIONS_PER_PAGE), offset: String(input.offset ?? 0) });
  if (input.older) {
    query.set('older', 'true');
  }
  if (level === 2) {
    query.set('level', '2');
  }
  const failureMessage = level === 2 ? 'Failed to load what you follow.' : 'Failed to load your notifications.';
  try {
    const response = await fetch(`${NOTIFICATIONS_API_PATH}?${query.toString()}`, {
      headers: { Accept: 'application/json' },
      ...(signal === undefined ? {} : { signal }),
    });
    const json: unknown = await response.json();
    const parsed = z.object({ data: z.array(notificationSchema), metadata: z.object({ pagination: paginationSchema }) }).safeParse(json);
    if (!response.ok || !parsed.success) {
      return { ok: false, status: response.status, message: failureMessage, errors: {} };
    }
    return { ok: true, message: '', data: { items: parsed.data.data, pagination: parsed.data.metadata.pagination } };
  } catch {
    return { ok: false, status: 0, message: failureMessage, errors: {} };
  }
}

const ownAccountSettingsSchema = z.object({
  time_zone: z.string().nullable().optional(),
  notification_popups: z.boolean().optional(),
});
type OwnAccountSettings = z.infer<typeof ownAccountSettingsSchema>;

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
const nameAvailabilitySchema = z.object({ available: z.boolean() });

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

/**
 * Adds an address to an EmailOctopus list through a public endpoint (the newsletter of the footer #938)
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
  newsletter: {
    /**
     * Subscribe to the newsletter (#938). The input and the answer keep the names of the waitlist, removed in #1574.
     */
    subscribe: (input: JoinWaitlistRequest): Promise<JoinWaitlistResponse> =>
      subscribeToList(NEWSLETTER_API_PATH, input, 'Failed to subscribe to the newsletter. Please try again later.'),
  },
  communities: {
    /**
     * Whether a shared community can take this name (#1136, ADR 0033). Pass `community` (a cuid) to check a new name
     * for a community that you own: its own name stays available to it.
     */
    isNameAvailable: (input: { name: string; community?: string }, signal?: AbortSignal): Promise<ApiResult<{ available: boolean }>> =>
      apiRequest('post', COMMUNITY_API_IS_NAME_AVAILABLE_PATH, nameAvailabilitySchema, 'Failed to check the name.', input, signal),
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
     * The owner gives the community to one of its admins, with their own password (#1339). Returns the community.
     */
    transferOwnership: (communityCuid: string, memberCuid: string, password: string): Promise<ApiResult<Community>> =>
      apiRequest('post', communityPath(COMMUNITY_OWNERSHIP_API_PATH, communityCuid), communitySchema, 'Failed to transfer the ownership.', {
        member: memberCuid,
        password,
      }),
    /**
     * Change who can join the community (owner and admins, #1486): by invitation or open. Returns the community.
     */
    updateJoinPolicy: (communityCuid: string, joinPolicy: CommunityJoinPolicy): Promise<ApiResult<Community>> =>
      apiRequest('patch', communityPath(COMMUNITY_JOIN_POLICY_API_PATH, communityCuid), communitySchema, 'Failed to save who can join.', {
        join_policy: joinPolicy,
      }),
    /**
     * Let search engines index the public spaces of the community, or not (owner and admins, #1487). Returns the community.
     */
    updateIndexing: (communityCuid: string, indexingEnabled: boolean): Promise<ApiResult<Community>> =>
      apiRequest(
        'patch',
        communityPath(COMMUNITY_INDEXING_API_PATH, communityCuid),
        communitySchema,
        'Failed to save the indexing setting.',
        {
          indexing_enabled: indexingEnabled,
        },
      ),
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
      update: (
        communityCuid: string,
        spaceCuid: string,
        input: Partial<Omit<SpaceForm, 'type' | 'default_notification_level'>> & { default_notification_level?: 'all' | 'mine' | null },
      ): Promise<ApiResult<Space>> =>
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
      create: (communityCuid: string, spaceCuid: string, input: PostCreateInput): Promise<ApiResult<Post>> =>
        apiRequest('post', postsPath(communityCuid, spaceCuid), postSchema, 'Failed to publish the post.', input),
      update: (communityCuid: string, spaceCuid: string, postCuid: string, input: Partial<PostForm>): Promise<ApiResult<Post>> =>
        apiRequest(
          'patch',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_API_PATH),
          postSchema,
          'Failed to save the post.',
          input,
        ),
      /**
       * Delete a post (#1565): a post with comments of other people stays as a marker (load it again to show it).
       * `delete_comments` (moderators only) deletes the post and its whole discussion.
       */
      delete: (
        communityCuid: string,
        spaceCuid: string,
        postCuid: string,
        options: { delete_comments?: boolean } = {},
      ): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_API_PATH),
          null,
          'Failed to delete the post.',
          options.delete_comments === undefined ? undefined : { delete_comments: options.delete_comments },
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
     * The events of events spaces (#1513, #1514, ADR 0053). The start and the end of a request carry the offset of the
     * zone ("2026-11-18T17:00:00+01:00"); the server answers a wrong time with a field error (400) and a refused rule
     * with its message (422).
     */
    events: {
      /**
       * A page of the events of a space. `upcoming`: the drafts that the reader may see first, then the next start
       * first. `past`: the newest start first. No description in a list.
       */
      list: (
        communityCuid: string,
        spaceCuid: string,
        scope: EventScope,
        limit: number = EVENTS_PER_PAGE,
        offset = 0,
      ): Promise<ApiResult<Paginated<SpaceEvent>>> =>
        apiPageRequest(
          `${eventsPath(communityCuid, spaceCuid)}?${new URLSearchParams({ scope, limit: String(limit), offset: String(offset) }).toString()}`,
          eventSchema,
          'Failed to load the events.',
        ),
      /**
       * The upcoming events of every events space of the community that the reader can read, the next start first,
       * never a draft
       */
      listUpcoming: (communityCuid: string, limit: number = EVENTS_PER_PAGE, offset = 0): Promise<ApiResult<Paginated<SpaceEvent>>> =>
        apiPageRequest(
          `${communityPath(COMMUNITY_EVENTS_API_PATH, communityCuid)}?${new URLSearchParams({ limit: String(limit), offset: String(offset) }).toString()}`,
          eventSchema,
          'Failed to load the events.',
        ),
      /**
       * One event, with its description. A 403 means "not for this reader" (a draft, a space that the reader may not
       * read), a 404 that the event is gone
       */
      get: (communityCuid: string, spaceCuid: string, eventCuid: string): Promise<ApiResult<SpaceEvent>> =>
        apiRequest(
          'get',
          eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_API_PATH),
          eventSchema,
          'Failed to load the event.',
        ),
      /**
       * Create an event: a draft, or published with `state`
       */
      create: (communityCuid: string, spaceCuid: string, input: EventRequest): Promise<ApiResult<SpaceEvent>> =>
        apiRequest('post', eventsPath(communityCuid, spaceCuid), eventSchema, 'Failed to save the event.', input),
      /**
       * Change the fields of an event. The state has its own call
       */
      update: (communityCuid: string, spaceCuid: string, eventCuid: string, input: EventUpdateRequest): Promise<ApiResult<SpaceEvent>> =>
        apiRequest(
          'patch',
          eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_API_PATH),
          eventSchema,
          'Failed to save the event.',
          input,
        ),
      /**
       * Publish, cancel, publish again or go back to draft. `notifyNow` (with `published`) tells the members now, for
       * the people who can moderate the space
       */
      setState: (
        communityCuid: string,
        spaceCuid: string,
        eventCuid: string,
        state: EventState,
        notifyNow = false,
      ): Promise<ApiResult<SpaceEvent>> =>
        apiRequest(
          'put',
          eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_STATE_API_PATH),
          eventSchema,
          'Failed to change the state of the event.',
          notifyNow ? { state, notify_now: true } : { state },
        ),
      /**
       * A draft copy, 7 days later at the same hour of the zone of the event
       */
      duplicate: (communityCuid: string, spaceCuid: string, eventCuid: string): Promise<ApiResult<SpaceEvent>> =>
        apiRequest(
          'post',
          eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_DUPLICATE_API_PATH),
          eventSchema,
          'Failed to duplicate the event.',
        ),
      delete: (communityCuid: string, spaceCuid: string, eventCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_API_PATH),
          null,
          'Failed to delete the event.',
        ),
      /**
       * The answer of the reader to an event (#1515): Yes, Replay only or No. Only a member of the community has one
       * (403 for another person). A cancelled event, an event that ended and a draft refuse a change with their words
       * (422), and a new Yes on a full event answers 422, "This event is full."
       */
      answer: {
        /**
         * `answer` is null when the member did not answer
         */
        get: (communityCuid: string, spaceCuid: string, eventCuid: string): Promise<ApiResult<EventAnswer>> =>
          apiRequest(
            'get',
            eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_ANSWER_API_PATH),
            eventAnswerSchema,
            'Failed to load your answer.',
          ),
        /**
         * Replaces the answer of the reader. The result holds no place count and no meeting address: get the event again
         */
        set: (communityCuid: string, spaceCuid: string, eventCuid: string, answer: EventAnswerValue): Promise<ApiResult<EventAnswer>> =>
          apiRequest(
            'put',
            eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_ANSWER_API_PATH),
            eventAnswerSchema,
            'Failed to save your answer.',
            { answer },
          ),
        /**
         * Removes the answer of the reader (204, also when there was none)
         */
        clear: (communityCuid: string, spaceCuid: string, eventCuid: string): Promise<ApiResult<undefined>> =>
          apiRequest(
            'delete',
            eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_ANSWER_API_PATH),
            null,
            'Failed to save your answer.',
          ),
      },
      /**
       * Who answered an event, for its host, the owner and the admins (403 for the others, #1515)
       */
      answers: {
        /**
         * A page of the members who answered Yes, or Replay only, by name, with the numbers of the three answers in
         * `counts`. The members who answered No are never named.
         */
        list: async (
          communityCuid: string,
          spaceCuid: string,
          eventCuid: string,
          answer: Exclude<EventAnswerValue, 'no'> = 'yes',
          limit: number = EVENT_ANSWERS_PER_PAGE,
          offset = 0,
        ): Promise<ApiResult<Paginated<EventAnswerMember> & { counts: EventAnswerCounts }>> => {
          const result = await apiPageRequestWith(
            `${eventsPath(communityCuid, spaceCuid, eventCuid, COMMUNITY_SPACE_EVENT_ANSWERS_API_PATH)}?${new URLSearchParams({ answer, limit: String(limit), offset: String(offset) }).toString()}`,
            eventAnswerMemberSchema,
            z.object({ counts: eventAnswerCountsSchema }),
            'Failed to load the answers.',
          );
          if (!result.ok) {
            return result;
          }
          const { items, pagination, metadata } = result.data;
          return { ok: true, message: result.message, data: { items, pagination, counts: metadata.counts } };
        },
      },
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
      /**
       * Your progress in a course space (#1304). Members of the community only.
       */
      progress: (communityCuid: string, spaceCuid: string): Promise<ApiResult<CourseProgress>> =>
        apiRequest(
          'get',
          pagesPath(communityCuid, spaceCuid, '', COMMUNITY_SPACE_PROGRESS_API_PATH),
          courseProgressSchema,
          'Failed to load your progress.',
        ),
      /**
       * Mark a lesson as done (#1304). Returns the progress of the course.
       */
      complete: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<CourseProgress>> =>
        apiRequest(
          'put',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_PROGRESS_API_PATH),
          courseProgressSchema,
          'Failed to mark the lesson as done.',
        ),
      /**
       * Mark a lesson as not done (#1304). Returns the progress of the course.
       */
      reopen: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<CourseProgress>> =>
        apiRequest(
          'delete',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_PROGRESS_API_PATH),
          courseProgressSchema,
          'Failed to mark the lesson as not done.',
        ),
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
      /**
       * Delete a comment (#1565): a comment with replies of other people stays as a marker. `delete_replies`
       * (moderators only) deletes the comment and every reply under it.
       */
      delete: (subject: CommentSubject, commentCuid: string, options: { delete_replies?: boolean } = {}): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          commentsPath(subject, commentCuid),
          null,
          'Failed to delete the comment.',
          options.delete_replies === undefined ? undefined : { delete_replies: options.delete_replies },
        ),
    },
    /**
     * The quiz of a lesson of a course space (#1335, ADR 0038): managers write it, members answer, the server grades
     */
    quizzes: {
      /**
       * The quiz of a lesson with your result. Managers of the course also get the correct choices.
       */
      get: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<LessonQuiz>> =>
        apiRequest(
          'get',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_QUIZ_API_PATH),
          lessonQuizSchema,
          'Failed to load the quiz.',
        ),
      /**
       * Create the quiz of a lesson or replace it as a whole (managers). New questions delete the results of the members.
       */
      save: (communityCuid: string, spaceCuid: string, pageCuid: string, input: QuizForm): Promise<ApiResult<LessonQuiz>> =>
        apiRequest(
          'put',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_QUIZ_API_PATH),
          lessonQuizSchema,
          'Failed to save the quiz.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, pageCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest(
          'delete',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_QUIZ_API_PATH),
          null,
          'Failed to delete the quiz.',
        ),
      /**
       * Answer the quiz: the server grades and returns your result with the review of this attempt, and your
       * progress in the course (a pass marks the lesson as done).
       */
      attempt: (communityCuid: string, spaceCuid: string, pageCuid: string, answers: QuizAnswer[]): Promise<ApiResult<QuizAttempt>> =>
        apiRequest(
          'post',
          pagesPath(communityCuid, spaceCuid, pageCuid, COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS_API_PATH),
          quizAttemptSchema,
          'Failed to send your answers.',
          { answers },
        ),
    },
    /**
     * Supplementary materials of course spaces (#1336, ADR 0037): files and links for the course, a section or a lesson
     */
    materials: {
      /**
       * Every material of the course space, in its order; each one names its section or lesson, or none
       */
      list: (communityCuid: string, spaceCuid: string, signal?: AbortSignal): Promise<ApiResult<CourseMaterial[]>> =>
        apiRequest(
          'get',
          materialsPath(communityCuid, spaceCuid),
          z.array(courseMaterialSchema),
          'Failed to load the materials.',
          undefined,
          signal,
        ),
      addLink: (
        communityCuid: string,
        spaceCuid: string,
        pageCuid: string | null,
        input: { title: string; url: string },
      ): Promise<ApiResult<CourseMaterial>> =>
        apiRequest('post', materialsPath(communityCuid, spaceCuid), courseMaterialSchema, 'Failed to add the link.', {
          kind: 'link',
          ...input,
          ...(pageCuid === null ? {} : { page: pageCuid }),
        }),
      /**
       * Upload a file (multipart). The server reads the type from the content and stores the file on a private disk.
       */
      addFile: async (
        communityCuid: string,
        spaceCuid: string,
        pageCuid: string | null,
        input: { title: string; file: File },
      ): Promise<ApiResult<CourseMaterial>> => {
        const failure = 'Failed to add the file.';
        const body = new FormData();
        body.append('kind', 'file');
        body.append('title', input.title);
        body.append('file', input.file);
        if (pageCuid !== null) {
          body.append('page', pageCuid);
        }
        try {
          // No Content-Type header: the browser sets the multipart boundary
          const response = await fetch(materialsPath(communityCuid, spaceCuid), {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body,
          });
          const json: unknown = await response.json();
          const envelope = typeof json === 'object' && json !== null ? (json as Record<string, unknown>) : {};
          const message = typeof envelope['message'] === 'string' && envelope['message'] !== '' ? envelope['message'] : failure;
          const parsed = courseMaterialSchema.safeParse(envelope['data']);
          if (!response.ok || !parsed.success) {
            return { ok: false, status: response.status, message, errors: fieldErrors(envelope['errors']) };
          }
          return { ok: true, message, data: parsed.data };
        } catch {
          return { ok: false, status: 0, message: failure, errors: {} };
        }
      },
      update: (
        communityCuid: string,
        spaceCuid: string,
        materialCuid: string,
        input: { title?: string; url?: string },
      ): Promise<ApiResult<CourseMaterial>> =>
        apiRequest(
          'patch',
          materialsPath(communityCuid, spaceCuid, COMMUNITY_SPACE_MATERIAL_API_PATH, materialCuid),
          courseMaterialSchema,
          'Failed to save the material.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, materialCuid: string): Promise<ApiResult<void>> =>
        apiRequest(
          'delete',
          materialsPath(communityCuid, spaceCuid, COMMUNITY_SPACE_MATERIAL_API_PATH, materialCuid),
          null,
          'Failed to delete the material.',
        ),
      /**
       * The new order of the materials of one target (the course: null, a section or a lesson). Returns every
       * material of the space.
       */
      reorder: (
        communityCuid: string,
        spaceCuid: string,
        pageCuid: string | null,
        materialCuids: string[],
      ): Promise<ApiResult<CourseMaterial[]>> =>
        apiRequest(
          'post',
          materialsPath(communityCuid, spaceCuid, COMMUNITY_SPACE_MATERIALS_ORDER_API_PATH),
          z.array(courseMaterialSchema),
          'Failed to reorder the materials.',
          { materials: materialCuids, ...(pageCuid === null ? {} : { page: pageCuid }) },
        ),
      /**
       * The address that downloads the file of a material (the server checks the access again)
       */
      downloadUrl: (communityCuid: string, spaceCuid: string, materialCuid: string): string =>
        materialsPath(communityCuid, spaceCuid, COMMUNITY_SPACE_MATERIAL_DOWNLOAD_API_PATH, materialCuid),
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
       * The current person joins an open community by themselves, as a member (#1486). Every refusal is a 422 with its message.
       * Returns the community as a member sees it.
       */
      join: (communityCuid: string): Promise<ApiResult<Community>> =>
        apiRequest('post', communityPath(COMMUNITY_MEMBERSHIP_API_PATH, communityCuid), communitySchema, 'Failed to join the community.'),
      /**
       * The people whom a manager removed, the most recent first (#1486).
       */
      joinBlocks: (communityCuid: string): Promise<ApiResult<CommunityJoinBlock[]>> =>
        apiRequest(
          'get',
          `${communityPath(COMMUNITY_JOIN_BLOCKS_API_PATH, communityCuid)}?limit=100`,
          z.array(communityJoinBlockSchema),
          'Failed to load the removed members.',
        ),
      /**
       * "May join again": the person can join the community by themselves again (#1486).
       */
      liftJoinBlock: (communityCuid: string, memberCuid: string): Promise<ApiResult<void>> =>
        apiRequest(
          'delete',
          apiPath(COMMUNITY_JOIN_BLOCK_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [COMMUNITY_API_PARAM_MEMBER]: memberCuid }),
          null,
          'Failed to let the member join again.',
        ),
      /**
       * The current member leaves the community (#1338). The owner gets a 422: they transfer the ownership first.
       */
      leave: (communityCuid: string): Promise<ApiResult<void>> =>
        apiRequest('delete', communityPath(COMMUNITY_MEMBERSHIP_API_PATH, communityCuid), null, 'Failed to leave the community.'),
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
      spaceGroups: {
        /**
         * The space groups that the group opens whole, in navigation order (#1422).
         */
        list: (communityCuid: string, groupCuid: string): Promise<ApiResult<AccessGroupSpaceGroup[]>> =>
          apiRequest(
            'get',
            accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_SPACE_GROUPS_API_PATH),
            z.array(accessGroupSpaceGroupSchema),
            'Failed to load the space groups of the access group.',
          ),
        /**
         * Replace the space groups that the group opens whole (#1422). An empty list removes them all.
         */
        set: (communityCuid: string, groupCuid: string, spaceGroupCuids: string[]): Promise<ApiResult<AccessGroupSpaceGroup[]>> =>
          apiRequest(
            'put',
            accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_SPACE_GROUPS_API_PATH),
            z.array(accessGroupSpaceGroupSchema),
            'Failed to save the space groups of the access group.',
            { space_groups: spaceGroupCuids },
          ),
      },
      rules: {
        /**
         * Replace the entitlement keys that give members the group (#1386, ADR 0047). An empty list removes every rule.
         */
        set: (communityCuid: string, groupCuid: string, entitlementKeys: string[]): Promise<ApiResult<{ entitlement_keys: string[] }>> =>
          apiRequest(
            'put',
            accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_RULES_API_PATH),
            z.object({ entitlement_keys: z.array(z.string()) }),
            'Failed to save the rules.',
            { entitlement_keys: entitlementKeys },
          ),
      },
      members: {
        /**
         * A page of the members of a group, by name, with the source of each membership (#1241, #1386).
         */
        list: async (
          communityCuid: string,
          groupCuid: string,
          limit: number,
          offset: number,
        ): Promise<ApiResult<Paginated<AccessGroupMember>>> => {
          const url = `${accessGroupPath(communityCuid, groupCuid, COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH)}?limit=${limit}&offset=${offset}`;
          try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const parsed = z
              .object({ data: z.array(accessGroupMemberSchema), metadata: z.object({ pagination: paginationSchema }) })
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
      toggle: (target: ReactionTarget, emoji: string): Promise<ApiResult<ReactionSummary>> =>
        apiRequest('post', reactionsPath(target), reactionSummarySchema, 'Failed to save the reaction.', { emoji }),
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
     * Delete a resource with its comments, reactions and captured content (#324): the curator, a member who manages
     * the spaces, or a moderator of the space
     */
    delete: (communityCuid: string, spaceCuid: string, resourceCuid: string): Promise<ApiResult<unknown>> =>
      apiRequest(
        'delete',
        apiPath(COMMUNITY_SPACE_RESOURCE_API_PATH, {
          [SPACE_API_PARAM_COMMUNITY]: communityCuid,
          [SPACE_API_PARAM_SPACE]: spaceCuid,
          [SPACE_API_PARAM_RESOURCE]: resourceCuid,
        }),
        null,
        'Failed to delete the resource.',
      ),
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
     * The emoji that the current member reacts with most (#1204): the "Frequently used" row of the picker
     */
    frequentEmoji: (): Promise<ApiResult<string[]>> =>
      apiRequest('get', USER_FREQUENT_EMOJI_API_PATH, z.array(reactionEmojiSchema), 'Failed to load your frequently used emoji.'),
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
    /**
     * The provider accounts that the current member connected (#1385). The sign-in itself is a browser flow
     */
    identities: {
      remove: (identityCuid: string): Promise<ApiResult<unknown>> =>
        apiRequest('delete', `${USER_IDENTITIES_API_PATH}/${encodeURIComponent(identityCuid)}`, null, 'Failed to disconnect the account.'),
    },
    /**
     * Set the time zone of the current member (#1476, ADR 0048): an IANA name like `Europe/Brussels`, or `UTC`. Only the member
     * sets it: the app calls this after a click, or one time when the account has no zone. Answers with the own account.
     */
    setTimeZone: (timeZone: string): Promise<ApiResult<OwnAccountSettings>> =>
      apiRequest('put', USER_TIME_ZONE_API_PATH, ownAccountSettingsSchema, 'Failed to save your time zone.', { time_zone: timeZone }),
    /**
     * Show or hide the pop-ups of the app for the current member (#1476). Answers with the own account.
     */
    setNotificationPopups: (popups: boolean): Promise<ApiResult<OwnAccountSettings>> =>
      apiRequest('put', USER_NOTIFICATION_SETTINGS_API_PATH, ownAccountSettingsSchema, 'Failed to save the pop-up choice.', { popups }),
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
  /**
   * The notifications of the current member, in every community (#1484, ADR 0042). The server holds the count: the client
   * never computes it.
   */
  notifications: {
    /**
     * A page of the "Needs you" notifications, newest news first. `older`: the ones that are older than 14 days.
     */
    list: (input: { limit?: number; offset?: number; older?: boolean } = {}, signal?: AbortSignal) => listNotifications(input, 1, signal),
    /**
     * A page of "Following" (level 2): the last 14 days, newest news first, never counted by the bell (#1497)
     */
    listFollowing: (input: { limit?: number; offset?: number } = {}, signal?: AbortSignal) => listNotifications(input, 2, signal),
    /**
     * "I am caught up": every open line of "Following" is read, in every community or in the one given. It never touches
     * "Needs you" (#1497).
     */
    caughtUp: (communityCuid?: string): Promise<ApiResult<{ marked: number }>> =>
      apiRequest(
        'post',
        NOTIFICATIONS_CAUGHT_UP_API_PATH,
        z.object({ marked: z.number().int() }),
        'Failed to clear Following.',
        communityCuid === undefined ? {} : { community: communityCuid },
      ),
    /**
     * The number of the bell: the notifications that the member did not see, 14 days old at most
     */
    count: (): Promise<ApiResult<{ count: number; messages: number }>> =>
      apiRequest(
        'get',
        NOTIFICATIONS_COUNT_API_PATH,
        // `messages`: the conversations with new messages, in every community (#1476). The tab title adds it to the bell number
        z.object({ count: z.number().int(), messages: z.number().int().default(0) }),
        'Failed to load the notification count.',
      ),
    /**
     * What the member missed in a community since a date, for the "Catch up" card (#1476): at most 5 lines and whether more
     * exists. Members of the community only. There is no count of missed things.
     */
    catchUp: (communityCuid: string, since: string, signal?: AbortSignal): Promise<ApiResult<CatchUp>> =>
      apiRequest(
        'get',
        `${communityPath(COMMUNITY_CATCH_UP_API_PATH, communityCuid)}?${new URLSearchParams({ since }).toString()}`,
        catchUpSchema,
        'Failed to load the catch-up.',
        undefined,
        signal,
      ),
    /**
     * The member visited a space (#1476): the dot of the navigation goes. The web app does not need it: the server writes the
     * visit when a page of the space opens.
     */
    visitSpace: (communityCuid: string, spaceCuid: string): Promise<ApiResult<undefined>> =>
      apiRequest(
        'put',
        apiPath(COMMUNITY_SPACE_VISIT_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid }),
        null,
        'Failed to save the visit.',
      ),
    /**
     * The lines were on screen
     */
    seen: (notificationCuids: string[]): Promise<ApiResult<undefined>> =>
      apiRequest('post', NOTIFICATIONS_SEEN_API_PATH, null, 'Failed to save what you saw.', { notification_cuids: notificationCuids }),
    /**
     * The line was opened or marked "Done"
     */
    read: (notificationCuid: string): Promise<ApiResult<Notification>> =>
      apiRequest(
        'put',
        apiPath(NOTIFICATION_READ_API_PATH, { [NOTIFICATION_API_PARAM_NOTIFICATION]: notificationCuid }),
        notificationSchema,
        'Failed to mark the notification as done.',
      ),
    /**
     * Undo "Done". A newer notification about the same thing makes it fail with status 422.
     */
    unread: (notificationCuid: string): Promise<ApiResult<Notification>> =>
      apiRequest(
        'post',
        apiPath(NOTIFICATION_UNREAD_API_PATH, { [NOTIFICATION_API_PARAM_NOTIFICATION]: notificationCuid }),
        notificationSchema,
        'Failed to undo.',
      ),
  },
  /**
   * The follow state of a thread for the current member, and the actions "Follow", "Stop following", "Mute this thread" and its undo
   * (#1497, #1509: the undo gives back the exact state before the mute). A thread is a post or a first-level chat message.
   */
  threads: {
    getPost: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'get',
        postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    followPost: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'put',
        postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    stopPost: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'delete',
        postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    mutePost: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'put',
        postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_MUTE_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    unmutePost: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'delete',
        postsPath(communityCuid, spaceCuid, postCuid, COMMUNITY_SPACE_POST_MUTE_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    getMessage: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'get',
        messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    followMessage: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'put',
        messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    stopMessage: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'delete',
        messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_FOLLOW_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    muteMessage: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'put',
        messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_MUTE_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
    unmuteMessage: (communityCuid: string, spaceCuid: string, messageCuid: string): Promise<ApiResult<ThreadFollow>> =>
      apiRequest(
        'delete',
        messagesPath(communityCuid, spaceCuid, messageCuid, COMMUNITY_SPACE_MESSAGE_MUTE_API_PATH),
        threadFollowSchema,
        FOLLOW_FAILURE,
      ),
  },
  /**
   * The level of the current member for the spaces: Everything, Only what is for me, Muted (#1497)
   */
  notificationLevels: {
    /**
     * The levels for every space of a community that the member can view, in the order of the navigation
     */
    forCommunity: (communityCuid: string): Promise<ApiResult<CommunitySpaceLevel[]>> =>
      apiRequest(
        'get',
        communityPath(COMMUNITY_NOTIFICATION_LEVELS_API_PATH, communityCuid),
        z.array(communitySpaceLevelSchema),
        'Failed to load the notification levels.',
      ),
    get: (communityCuid: string, spaceCuid: string): Promise<ApiResult<SpaceLevel>> =>
      apiRequest('get', spaceLevelPath(communityCuid, spaceCuid), spaceLevelSchema, 'Failed to load the notification level.'),
    set: (communityCuid: string, spaceCuid: string, level: SpaceNotificationLevel): Promise<ApiResult<SpaceLevel>> =>
      apiRequest('put', spaceLevelPath(communityCuid, spaceCuid), spaceLevelSchema, 'Failed to save the notification level.', { level }),
    /**
     * Forget the choice: the default of the space applies again
     */
    reset: (communityCuid: string, spaceCuid: string): Promise<ApiResult<SpaceLevel>> =>
      apiRequest('delete', spaceLevelPath(communityCuid, spaceCuid), spaceLevelSchema, 'Failed to reset the notification level.'),
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
