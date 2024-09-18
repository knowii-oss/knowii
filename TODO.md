# TODO

- Create new UserResource to serialize only what we need for other users and ensure that Inertia props.user... does not use it (otherwise would break CurrentUser and the UpdateUserProfileInformation page)
  - New User client-side type for representing other users, limited information: "User" with a Zod userSchema
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
- add constants for API paths on the server side and use those in api.php and in tests

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
