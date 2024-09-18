import { COMMUNITY_API_BASE_PATH, PING_API_PATH, USERS_API_IS_USERNAME_AVAILABLE_PATH } from '../constants';
import { PingResponse, pingResponseSchema } from './communities/ping-response.schema';
import { CreateCommunityResponse, createCommunityResponseSchema } from './communities/create-community-response.schema';
import { CreateCommunityRequest } from './communities/create-community-request.schema';
import { IsUsernameAvailableRequest } from './users/is-username-available-request.schema';
import { IsUsernameAvailableResponse, isUsernameAvailableResponseSchema } from './users/is-username-available-response.schema';

const defaultHeaders: Headers = new Headers({
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

export const knowiiApiClient = {
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
};
