# TODO

- Add controller to description field
- Try to use Zod again
- Community creation: https://github.com/knowii-oss/knowii/issues/702
  - Implement isCommunityName available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/is-community-name-available.dao.fn.ts
  - Add slug
  - Throw BusinessException if community slug is already taken
  - Implement isCommunitySlug available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/is-community-slug-available.dao.fn.ts
  - Inspiration: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/create-community.dao.fn.ts
- API: Collections & pagination
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/CRUD-Retrieve-Collection
  - Customize output: https://laravel.com/docs/11.x/eloquent-resources#customizing-the-pagination-information
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-About
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Rules-and-metadata
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Example
  - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Out-of-range-bounds
- Implement isUsername available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/api/is-username-available.schema.ts
- Reset prod DB
- TODO adapt CommunityPolicy rules
  Places to update when modifying the Community model

- PHP
  - Migration file
  - Community model
  - CreateCommunity
  - CommunityController (inertia)
  - CommunityApiController
  - CommunityResource
  - Factories
  - Tests
- Frontend
  - common/src/lib/api/communities
  - common/src/lib/types/community.schema.ts
  - Community screens
- Bruno requests/tests/...
- complete community schema: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/generated/prisma/modelSchema/CommunitiesSchema.ts
- Add waitlist form to landing page: https://github.com/knowii-oss/knowii/issues/714
  - EmailOctopus list?
- Remove unused errorbags
