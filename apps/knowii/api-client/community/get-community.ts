import { API_BASE_PATH, COMMUNITY_BASE_URL, GetCommunityResponse, GetCommunityResponseData, IS_DEV, IS_SERVER } from '@knowii/common';

export async function getCommunity(slug: string, host?: string): Promise<GetCommunityResponseData> {
  let requestUrl = `${API_BASE_PATH}${COMMUNITY_BASE_URL}/${slug}`;
  if (IS_SERVER) {
    const protocol = IS_DEV ? 'http' : 'https';
    requestUrl = `${protocol}://${host}${requestUrl}`;
  }

  const response = await fetch(requestUrl);

  const getCommunityResponse: GetCommunityResponse = await response.json();

  let retVal: GetCommunityResponseData | null = null;

  if (response.ok) {
    // TODO validate response structure with Zod
    const foundCommunity = getCommunityResponse.data;

    if (foundCommunity) {
      retVal = foundCommunity;
      return retVal;
    }

    return Promise.reject({
      error: 'Not found',
      errorDescription: 'Could not find a community with that slug',
      errorDetails: `Could not find a community with the following slug: ${slug}`,
    });
  } else {
    return Promise.reject({
      error: getCommunityResponse.error,
      errorDescription: getCommunityResponse.errorDescription,
      errorDetails: getCommunityResponse.errorDetails,
    });
  }
}
