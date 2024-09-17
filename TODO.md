# TODO

- Ensure community slug uniqueness
-
- Add username generation
  - Create new UserResource to serialize only what we need for other users
  - New User client-side type for representing other users -> limited information
    - hide email from serializable User fields?
  - Implement isUsername available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/api/is-username-available.schema.ts
    - https://github.com/knowii-oss/knowii/issues/630
  - UpdateUserProfileInformation
  - UpdateProfileInformationForm
  - ProfileInformationTest
  - CommunityMemberController ??
  - Community - hasUserWithEmail ??
  - jetstream-inertia.intf.ts
- Increase community box sizes
- https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/apps/knowii/pages/communities/create/index.tsx
- Community creation: https://github.com/knowii-oss/knowii/issues/702
  - Add community name regex (back and front + tests)
  - Ensure slug uniqueness when creating
- CreateNewUser should use CreateCommunity to create the user's personal space
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
- Bruno requests/tests/...
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
