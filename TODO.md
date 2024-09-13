# TODO

Improve LoginController data output

- message
- data
- errors (returned after validation)
- Handle exceptions when saving data in the database

- API Design: https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki

  - Error handling
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-About
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Expectations
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Status-codes
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Error-details
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Example-with-a-single-error
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Example-with-multiple-errors
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Example-with-parameters
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Example-with-additional-metadata
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Warnings
    -
  - Pagination
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-About
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Rules-and-metadata
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Example
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Pagination-Out-of-range-bounds
  - Collections
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/CRUD-Retrieve-Collection
  - Single item
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/CRUD-Retrieve-Single-item

- catch BusinessException and TechnicalException in the render method of bootstrap\app.php

- Implement isUsername available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/api/is-username-available.schema.ts
- Create API client: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/client/src/lib/api-client/community/get-community.ts
- GET Community list API: https://github.com/knowii-oss/knowii/issues/784
- Throw BusinessException if community slug is already taken
- Create community from dashboard: https://github.com/knowii-oss/knowii/issues/786

  - Create community API, take inspiration from: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/create-community.dao.fn.ts
  - Second: https://github.com/knowii-oss/knowii/issues/702 (includes code)

    - Clean community name
    - Implement isCommunityName available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/is-community-name-available.dao.fn.ts
    - Implement isCommunitySlug available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/server/src/lib/dao/communities/is-community-slug-available.dao.fn.ts
    - Rate limiting for those calls (to avoid user enumeration)

  - Reset prod DB

Customize pagination information: https://laravel.com/docs/11.x/eloquent-resources#customizing-the-pagination-information

Places to update when modifying the Community model

- PHP
  - Migration file
  - Community model
  - CreateCommunity implements CreatesCommunities
  - ...
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
- OpenAPI documentation
- complete community schema: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/generated/prisma/modelSchema/CommunitiesSchema.ts
- Document API endpoint creation process: https://github.com/knowii-oss/knowii/issues/740
- Add tests for the API: https://github.com/knowii-oss/knowii/issues/725
- Add waitlist form to landing page: https://github.com/knowii-oss/knowii/issues/714
  - EmailOctopus list?
