import {
  API_BASE_PATH,
  ApiErrors,
  COMMUNITY_BASE_URL,
  errorCommunityNotFound,
  GetCommunityResponse,
  GetCommunityResponseData,
  IS_DEV,
  IS_SERVER,
} from '@knowii/common';

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
    const foundCommunity = getCommunityResponse.data;

    if (foundCommunity) {
      retVal = foundCommunity;
      return retVal;
    }

    const apiError: ApiErrors = {
      type: errorCommunityNotFound.type,
      category: errorCommunityNotFound.category,
      title: errorCommunityNotFound.code,
      titleKey: errorCommunityNotFound.key,
      errorDetails: [
        {
          detail: errorCommunityNotFound.description,
          detailKey: errorCommunityNotFound.key,
          status: errorCommunityNotFound.statusCode,
        },
      ],
    };

    return Promise.reject(apiError);
  } else {
    return Promise.reject(getCommunityResponse.errors);
  }
}
