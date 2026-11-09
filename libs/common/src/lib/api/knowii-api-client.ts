import {
  COMMUNITY_API_BASE_PATH,
  COMMUNITY_API_PATH,
  COMMUNITY_API_PATH_PARAM_COMMUNITY,
  COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH,
  COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH_PARAM_COMMUNITY,
  COMMUNITY_RESOURCE_COLLECTION_API_PATH,
  COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_COMMUNITY,
  COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_RESOURCE_COLLECTION,
  COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH,
  COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_COMMUNITY,
  COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_RESOURCE_COLLECTION,
  COMMUNITY_SPACE_API_PATH,
  COMMUNITY_SPACE_GROUP_API_PATH,
  COMMUNITY_SPACE_GROUP_SPACES_REORDER_API_PATH,
  COMMUNITY_SPACE_GROUPS_API_PATH,
  COMMUNITY_SPACE_GROUPS_REORDER_API_PATH,
  COMMUNITY_SPACES_API_PATH,
  COMMUNITY_SPACE_POST_API_PATH,
  COMMUNITY_SPACE_POST_COMMENT_API_PATH,
  COMMUNITY_SPACE_POST_COMMENTS_API_PATH,
  SPACE_API_PARAM_COMMENT,
  COMMUNITY_SPACE_POST_PIN_API_PATH,
  COMMUNITY_SPACE_POSTS_API_PATH,
  MARKDOWN_PREVIEW_API_PATH,
  SPACE_API_PARAM_POST,
  PING_API_PATH,
  SPACE_API_PARAM_COMMUNITY,
  SPACE_API_PARAM_SPACE,
  SPACE_API_PARAM_SPACE_GROUP,
  USERS_API_IS_USERNAME_AVAILABLE_PATH,
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
import type { CreateCommunityResourceCollectionRequest } from './community-resource-collections/create-community-resource-collection-request.schema';
import type { CreateCommunityResourceCollectionResponse } from './community-resource-collections/create-community-resource-collection-response.schema';
import { createCommunityResourceCollectionResponseSchema } from './community-resource-collections/create-community-resource-collection-response.schema';
import type { CreateResourceTextArticleRequest } from './resources/create-resource-text-article-request.schema';
import type { CreateResourceTextArticleResponse } from './resources/create-resource-text-article-response.schema';
import { createResourceTextArticleResponseSchema } from './resources/create-resource-text-article-response.schema';
import { HttpStatus } from '../types/http-status.intf';
import type { DeleteResourceCollectionRequest } from './community-resource-collections/delete-community-resource-collection-request.schema';
import type { DeleteCommunityRequest } from './communities/delete-community-request.schema';
import type { JoinWaitlistRequest } from './waitlist/join-waitlist-request.schema';
import type { JoinWaitlistResponse } from './waitlist/join-waitlist-response.schema';
import { joinWaitlistResponseSchema } from './waitlist/join-waitlist-response.schema';
import { z } from 'zod';
import type { ApiResult } from './api-result';
import { apiPath, apiRequest } from './api-result';
import type { Space, SpaceForm, SpaceGroup, SpaceGroupForm } from '../types/space.schema';
import { spaceGroupSchema, spaceSchema } from '../types/space.schema';
import type { Paginated, Post, PostForm } from '../types/post.schema';
import { paginationSchema, postSchema } from '../types/post.schema';
import type { Comment, CommentForm } from '../types/comment.schema';
import { commentSchema } from '../types/comment.schema';

const spaceGroupPath = (communityCuid: string, spaceGroupCuid: string, path = COMMUNITY_SPACE_GROUP_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE_GROUP]: spaceGroupCuid });
const spacePath = (communityCuid: string, spaceCuid: string) =>
  apiPath(COMMUNITY_SPACE_API_PATH, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid });
const communityPath = (path: string, communityCuid: string) => apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid });
const postsPath = (communityCuid: string, spaceCuid: string, postCuid = '', path = COMMUNITY_SPACE_POSTS_API_PATH) =>
  apiPath(path, { [SPACE_API_PARAM_COMMUNITY]: communityCuid, [SPACE_API_PARAM_SPACE]: spaceCuid, [SPACE_API_PARAM_POST]: postCuid });

const postListSchema = z.array(postSchema);
const commentsPath = (communityCuid: string, spaceCuid: string, postCuid: string, commentCuid?: string) =>
  apiPath(commentCuid ? COMMUNITY_SPACE_POST_COMMENT_API_PATH : COMMUNITY_SPACE_POST_COMMENTS_API_PATH, {
    [SPACE_API_PARAM_COMMUNITY]: communityCuid,
    [SPACE_API_PARAM_SPACE]: spaceCuid,
    [SPACE_API_PARAM_POST]: postCuid,
    [SPACE_API_PARAM_COMMENT]: commentCuid ?? '',
  });
const htmlSchema = z.object({ html: z.string() });

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

export const knowiiApiClient = {
  waitlist: {
    join: async (input: JoinWaitlistRequest): Promise<JoinWaitlistResponse> => {
      const failure: JoinWaitlistResponse = {
        message: 'Failed to join the waitlist. Please try again later.',
        errors: [],
        type: 'internalError',
        category: 'technical',
      };

      try {
        const response = await fetch(WAITLIST_API_PATH, {
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const parsedResponse = joinWaitlistResponseSchema.safeParse(await response.json());

        return parsedResponse.success ? parsedResponse.data : failure;
      } catch {
        return failure;
      }
    },
  },
  communities: {
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
    spaces: {
      create: (communityCuid: string, input: SpaceForm): Promise<ApiResult<Space>> =>
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
    comments: {
      list: (communityCuid: string, spaceCuid: string, postCuid: string): Promise<ApiResult<Comment[]>> =>
        apiRequest('get', commentsPath(communityCuid, spaceCuid, postCuid), z.array(commentSchema), 'Failed to load the comments.'),
      create: (
        communityCuid: string,
        spaceCuid: string,
        postCuid: string,
        input: CommentForm & { parent_cuid?: string | null },
      ): Promise<ApiResult<Comment>> =>
        apiRequest('post', commentsPath(communityCuid, spaceCuid, postCuid), commentSchema, 'Failed to publish the comment.', input),
      update: (
        communityCuid: string,
        spaceCuid: string,
        postCuid: string,
        commentCuid: string,
        input: CommentForm,
      ): Promise<ApiResult<Comment>> =>
        apiRequest(
          'patch',
          commentsPath(communityCuid, spaceCuid, postCuid, commentCuid),
          commentSchema,
          'Failed to save the comment.',
          input,
        ),
      delete: (communityCuid: string, spaceCuid: string, postCuid: string, commentCuid: string): Promise<ApiResult<undefined>> =>
        apiRequest('delete', commentsPath(communityCuid, spaceCuid, postCuid, commentCuid), null, 'Failed to delete the comment.'),
    },
    resourceCollections: {
      create: async (input: CreateCommunityResourceCollectionRequest): Promise<CreateCommunityResourceCollectionResponse> => {
        const requestUrl = COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH.replace(
          COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH_PARAM_COMMUNITY,
          input.communityCuid,
        );

        try {
          const response = await fetch(requestUrl, {
            method: 'post',
            headers: defaultHeaders,
            body: JSON.stringify(input),
          });

          const responseAsJson = await response.json();

          const parsedResponse = createCommunityResourceCollectionResponseSchema.safeParse(responseAsJson);

          if (parsedResponse.success) {
            return parsedResponse.data;
          }

          return {
            message: responseAsJson.message ? responseAsJson.message : 'Failed to create the resource collection. Please try again later.',
            errors: parsedResponse.error.issues,
            type: responseAsJson.type ? responseAsJson.type : 'internalError',
            category: responseAsJson.category ? responseAsJson.category : 'technical',
          };
        } catch {
          return {
            message: 'Failed to create the resource collection. Please try again later.',
            errors: [],
            type: 'internalError',
            category: 'technical',
          };
        }
      },
      delete: async (input: DeleteResourceCollectionRequest): Promise<boolean> => {
        const requestUrl = COMMUNITY_RESOURCE_COLLECTION_API_PATH.replace(
          COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_COMMUNITY,
          input.community.cuid,
        ).replace(COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_RESOURCE_COLLECTION, input.resourceCollection.cuid);

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
    },
  },
  resources: {
    createTextArticle: async (input: CreateResourceTextArticleRequest): Promise<CreateResourceTextArticleResponse> => {
      const requestUrl = COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH.replace(
        COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_COMMUNITY,
        input.communityCuid,
      ).replace(COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_RESOURCE_COLLECTION, input.resourceCollectionCuid);

      try {
        const response = await fetch(requestUrl, {
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const responseAsJson = await response.json();

        const parsedResponse = createResourceTextArticleResponseSchema.safeParse(responseAsJson);

        if (parsedResponse.success) {
          return parsedResponse.data;
        }

        return {
          message: responseAsJson.message ? responseAsJson.message : 'Failed to create the text article. Please try again later.',
          errors: parsedResponse.error.issues,
          type: responseAsJson.type ? responseAsJson.type : 'internalError',
          category: responseAsJson.category ? responseAsJson.category : 'technical',
        };
      } catch {
        return {
          message: 'Failed to create the text article. Please try again later.',
          errors: [],
          type: 'internalError',
          category: 'technical',
        };
      }
    },
  },
  users: {
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
    preview: async (markdown: string, signal?: AbortSignal): Promise<string | null> => {
      try {
        const response = await fetch(MARKDOWN_PREVIEW_API_PATH, {
          method: 'post',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ markdown }),
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
