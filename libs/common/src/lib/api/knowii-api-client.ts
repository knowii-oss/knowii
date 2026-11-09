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

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

export const knowiiApiClient = {
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
          errors: parsedResponse.error.errors,
          type: responseAsJson.type ? responseAsJson.type : 'internalError',
          category: responseAsJson.category ? responseAsJson.category : 'technical',
        };
      } catch (error) {
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
      } catch (error) {
        return false;
      }
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
            errors: parsedResponse.error.errors,
            type: responseAsJson.type ? responseAsJson.type : 'internalError',
            category: responseAsJson.category ? responseAsJson.category : 'technical',
          };
        } catch (error) {
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
        } catch (error) {
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
          errors: parsedResponse.error.errors,
          type: responseAsJson.type ? responseAsJson.type : 'internalError',
          category: responseAsJson.category ? responseAsJson.category : 'technical',
        };
      } catch (error) {
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
          signal,
          method: 'post',
          headers: defaultHeaders,
          body: JSON.stringify(input),
        });

        const responseAsJson = await response.json();

        return isUsernameAvailableResponseSchema.parse(responseAsJson);
      } catch (error) {
        return {
          message: 'Failed to check if the username is available. Please try again later.',
          errors: [],
          type: 'internalError',
          category: 'technical',
        };
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
      } catch (error) {
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
