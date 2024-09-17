# TODO

- Implement isUsername available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/api/is-username-available.schema.ts
  - https://github.com/knowii-oss/knowii/issues/630
  - Usage inspiration (client-side): https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/apps/knowii/pages/communities/create/index.tsx
- Add possibility to change username: UpdateUserProfileInformation, maybe via UpdateProfileInformationForm
  - Integrate name availability check
  - Submit form to update the username (not via the API)
- Make sure this still works: ProfileInformationTest
- Create new UserResource to serialize only what we need for other users and ensure that Inertia props.user... does not use it (otherwise would break CurrentUser and the UpdateUserProfileInformation page)
- New User client-side type for representing other users, limited information: "User" with a Zod userSchema
- CommunityMemberController ??
- Community - hasUserWithEmail ??
- Add updateCommunity API endpoint
- Fix bug: https://github.com/knowii-oss/knowii/issues/814
  - either remove the CommunityController that loads the initial data, and let it load the data itself on load
  - or use this to persist the updated state: https://inertiajs.com/remembering-state
- Review JetstreamServiceProvider roles
  - editor??
- API: Collections & pagination
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/CRUD-Retrieve-Collection
  - Customize output: https://laravel.com/docs/11.x/eloquent-resources#customizing-the-pagination-information
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-About
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Rules-and-metadata
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Example
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Out-of-range-bounds
- TODO adapt CommunityPolicy rules
- Reset prod DB
- Update Bruno requests/tests/...
- complete community schema: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/generated/prisma/modelSchema/CommunitiesSchema.ts
- Add waitlist form to landing page: https://github.com/knowii-oss/knowii/issues/714
  - EmailOctopus list?
- Remove unused errorbags

import { z } from 'zod';
import { allowedCommunitySlugCharactersRegex, maxLengthCommunitySlug, minLengthCommunitySlug } from '../../constants';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const isCommunitySlugAvailableRequestSchema = z.object({
slugToCheck: z.string().regex(allowedCommunitySlugCharactersRegex).min(minLengthCommunitySlug).max(maxLengthCommunitySlug),
});

export type IsCommunitySlugAvailableRequest = z.infer<typeof isCommunitySlugAvailableRequestSchema>;

export const isCommunitySlugAvailableResponseDataSchema = z.object({
isSlugAvailable: z.boolean(),
});

export type IsCommunitySlugAvailableResponseData = z.infer<typeof isCommunitySlugAvailableResponseDataSchema>;

export const isCommunitySlugAvailableResponseSchema = singleItemApiResponseSchema(isCommunitySlugAvailableResponseDataSchema, z.object({}));

export type IsCommunitySlugAvailableResponse = z.infer<typeof isCommunitySlugAvailableResponseSchema>;
