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
  PING_API_PATH,
  USERS_API_IS_USERNAME_AVAILABLE_PATH,
} from '../constants';
import { PingResponse, pingResponseSchema } from './ping-response.schema';
import { CreateCommunityResponse, createCommunityResponseSchema } from './communities/create-community-response.schema';
import { CreateCommunityRequest } from './communities/create-community-request.schema';
import { IsUsernameAvailableRequest } from './users/is-username-available-request.schema';
import { IsUsernameAvailableResponse, isUsernameAvailableResponseSchema } from './users/is-username-available-response.schema';
import { CreateCommunityResourceCollectionRequest } from './community-resource-collections/create-community-resource-collection-request.schema';
import {
  CreateCommunityResourceCollectionResponse,
  createCommunityResourceCollectionResponseSchema,
} from './community-resource-collections/create-community-resource-collection-response.schema';
import { CreateResourceTextArticleRequest } from './resources/create-resource-text-article-request.schema';
import {
  CreateResourceTextArticleResponse,
  createResourceTextArticleResponseSchema,
} from './resources/create-resource-text-article-response.schema';
import { HttpStatus } from '../types/http-status.intf';
import { DeleteResourceCollectionRequest } from './community-resource-collections/delete-community-resource-collection-request.schema';
import { DeleteCommunityRequest } from './communities/delete-community-request.schema';

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

export const knowiiApiClient = {
  communities: {
    create: async (input: CreateCommunityRequest): Promise<CreateCommunityResponse> => {
      const response = await fetch(COMMUNITY_API_BASE_PATH, {
        method: 'post',
        headers: defaultHeaders,
        body: JSON.stringify(input),
      });

      const responseAsJson = await response.json();
      return createCommunityResponseSchema.parse(responseAsJson);
    },
    delete: async (input: DeleteCommunityRequest): Promise<boolean> => {
      const requestUrl = COMMUNITY_API_PATH.replace(COMMUNITY_API_PATH_PARAM_COMMUNITY, input.community.cuid);

      const response = await fetch(requestUrl, {
        method: 'delete',
        headers: defaultHeaders,
      });

      return response.status === HttpStatus.NO_CONTENT;
    },
    resourceCollections: {
      create: async (input: CreateCommunityResourceCollectionRequest): Promise<CreateCommunityResourceCollectionResponse> => {
        const requestUrl = COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH.replace(
          COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH_PARAM_COMMUNITY,
          input.communityCuid,
        );

        const response = await fetch(requestUrl, {
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const responseAsJson = await response.json();
        return createCommunityResourceCollectionResponseSchema.parse(responseAsJson);
      },
      delete: async (input: DeleteResourceCollectionRequest): Promise<boolean> => {
        const requestUrl = COMMUNITY_RESOURCE_COLLECTION_API_PATH.replace(
          COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_COMMUNITY,
          input.community.cuid,
        ).replace(COMMUNITY_RESOURCE_COLLECTION_API_PATH_PARAM_RESOURCE_COLLECTION, input.resourceCollection.cuid);

        const response = await fetch(requestUrl, {
          method: 'delete',
          headers: defaultHeaders,
        });

        return response.status === HttpStatus.NO_CONTENT;
      },
    },
  },
  resources: {
    createTextArticle: async (input: CreateResourceTextArticleRequest): Promise<CreateResourceTextArticleResponse> => {
      const requestUrl = COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH.replace(
        COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_COMMUNITY,
        input.communityCuid,
      ).replace(COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_RESOURCE_COLLECTION, input.resourceCollectionCuid);

      const response = await fetch(requestUrl, {
        method: 'post',
        headers: defaultHeaders,
        body: JSON.stringify(input),
      });

      const responseAsJson = await response.json();

      return createResourceTextArticleResponseSchema.parse(responseAsJson);
    },
  },
  users: {
    isUsernameAvailable: async (input: IsUsernameAvailableRequest, signal?: AbortSignal): Promise<IsUsernameAvailableResponse> => {
      const response = await fetch(USERS_API_IS_USERNAME_AVAILABLE_PATH, {
        signal,
        method: 'post',
        headers: defaultHeaders,
        body: JSON.stringify(input),
      });

      const responseAsJson = await response.json();

      return isUsernameAvailableResponseSchema.parse(responseAsJson);
    },
  },
  utils: {
    ping: async (): Promise<PingResponse> => {
      const response = await fetch(PING_API_PATH, {
        method: 'get',
        headers: defaultHeaders,
      });

      const responseAsJson = await response.json();

      return pingResponseSchema.parse(responseAsJson);
    },
  },
};
