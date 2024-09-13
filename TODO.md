# TODO

Transform ValidationError

Read
https://laracasts.com/discuss/channels/laravel/need-to-create-custom-validationexception?page=1&replyId=772308
https://stackoverflow.com/questions/35097371/laravel-validation-error-customise-format-of-the-response
https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/client/src/lib/api-client/community/get-community.ts

CreateCommunity.php
CommunityApiController.php
ApiResponses.php
KnowiiValidationException.php
api-errors.schema.ts
api-error.schema.ts
single-item-api-response.schema.ts

Transform other exceptions

{
message
metadata
data
errors: {
"field_name": [
"message"
]
}
}

- Handle exceptions when saving data in the database

- API Design: https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki

  - Error handling
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-About
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Expectations
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Status-codes
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Error-details
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
    - Customize output: https://laravel.com/docs/11.x/eloquent-resources#customizing-the-pagination-information
  - Single item
    - https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/CRUD-Retrieve-Single-item

- catch BusinessException and TechnicalException in the render method of bootstrap\app.php

- Implement isUsername available API: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/api/is-username-available.schema.ts
- Create API client: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/client/src/lib/api-client/community/get-community.ts
-
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
- complete community schema: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/libs/common/src/lib/generated/prisma/modelSchema/CommunitiesSchema.ts
- Document API endpoint creation process: https://github.com/knowii-oss/knowii/issues/740
- Add tests for the API: https://github.com/knowii-oss/knowii/issues/725
- Add waitlist form to landing page: https://github.com/knowii-oss/knowii/issues/714
  - EmailOctopus list?
- Remove errorbags

->withExceptions(function (Exceptions $exceptions) {
$exceptions->render(function (InvalidOrderException $e, Request $request) {
return response()->view('errors.invalid-order', status: 500);
});
})

$exceptions->render(function (NotFoundHttpException $e, Request $request) {
  if ($request->is('api/\*')) {
return response()->json([
'message' => 'Record not found.'
], 404);
}
});

->withExceptions(function (Exceptions $exceptions) {
  $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
    if ($request->is('admin/\*')) {
return true;
}
return $request->expectsJson();
});
})

types:
authentication
authorization
notAvailable
notFound
server
validation

categories:
business
security
technical

/\*\*

- An error with basic information
  \*/
  interface ReusableError {
  code: string;
  key: string;
  description: string;
  statusCode: number;
  type: ErrorType;
  category: ErrorCategory;
  }

export const errorInternalServerError: ReusableError = {
code: 'internal_server_error',
key: 'errors.internalServerError',
description: 'We have encountered an unexpected issue',
statusCode: 500,
type: 'server',
category: 'technical',
};

export const errorClientNotAuthenticated: ReusableError = {
code: 'client_not_authenticated',
key: 'errors.clientNotAuthenticated',
description: 'The client does not have an active session or is not authenticated',
statusCode: 401,
type: 'authentication',
category: 'security',
};

export const errorInputValidation: ReusableError = {
code: 'invalid_request_error',
key: 'errors.invalidRequestError',
description: 'The provided request data is incomplete or invalid',
statusCode: 400,
type: 'validation',
category: 'business',
};

export const errorCommunityNotFound: ReusableError = {
code: 'community_not_found_error',
key: 'communityNotFoundError',
description: 'The community could not be found',
statusCode: 404,
type: 'notFound',
category: 'business',
};

export const errorCommunityNameNotAvailable: ReusableError = {
code: 'community_name_not_available',
key: 'communityNameNotAvailable',
description: 'The chosen community name is not available',
statusCode: 409,
type: 'notAvailable',
category: 'business',
};

class InvalidOrderException extends Exception
{
/\*\*

- Render the exception into an HTTP response.
  _/
  public function render(Request $request): Response
  {
  return response(/_ ... \*/);
  }
  }
