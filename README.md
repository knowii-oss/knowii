# Knowii

Welcome to Knowii, the place for your Knowledge, Ideas and Innovation

## Contributing

Knowii is open source. We are always looking for new contributors. Check out the [contributing document](CONTRIBUTING.md) to know how.

## API
In addition to the user interface, Knowii has an API, available at `/api/v1`.

There are two ways to use it:
- Using API tokens (recommended)
- Using cookies

To use the API using cookies, you first need to call the login endpoint at `/api/v1/login`, passing the `email` and `password` fields in the body of the request. Once submitted, your API client will receive a cookie in return. Your next API calls will be authenticated if you include the cookie.

To use the API using tokens, you need to generate an API token, either through the Web interface, or by calling the `/api/v1/login` endpoint, adding `includeToken: true` to the body of your request. If you do that, then you'll get a token back in the `token` field of the response. Tokens generating using the login endpoint are valid for 12 hours. Those generated through the Web interface may be valid for much longer.
Once you have a token, you can call other API endpoints and add the `Authorization` header with `Bearer <your token>` as the value.

## License

Knowii is licensed under the terms of the AGPL [open source license](LICENSE).
