import { COMMUNITY_API_BASE_PATH, PING_API_PATH } from '../constants';
import { PingResponse, pingResponseSchema } from './communities/ping-response.schema';
import { CreateCommunityResponse, createCommunityResponseSchema } from './communities/create-community-response.schema';
import { CreateCommunityRequest } from './communities/create-community-request.schema';

export const knowiiApiClient = {
  ping: async (): Promise<PingResponse> => {
    const response = await fetch(PING_API_PATH, {
      method: 'get',
      headers: new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
    });

    const responseAsJson = await response.json();

    return pingResponseSchema.parse(responseAsJson);
  },
  createCommunity: async (input: CreateCommunityRequest): Promise<CreateCommunityResponse> => {
    const response = await fetch(COMMUNITY_API_BASE_PATH, {
      method: 'post',
      headers: new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify(input),
    });

    const responseAsJson = await response.json();

    return createCommunityResponseSchema.parse(responseAsJson);
  },
};
