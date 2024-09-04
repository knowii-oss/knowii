# 0.0.0 (2024-09-04)


### Bug Fixes

* **all:** fixed API_BASE_PATH usage to avoid // and needless redirects ([b7c946a](https://github.com/DeveloPassion/knowii/commit/b7c946a284690778cdcdd55bf5c91654c11c0c8c))
* **all:** switched back to Pino and fixed the issue ([c929451](https://github.com/DeveloPassion/knowii/commit/c929451829d620c25a29b8f5d719dc0309d183d2)), closes [#623](https://github.com/DeveloPassion/knowii/issues/623)
* **code:** adapt page height ([a47e2ad](https://github.com/DeveloPassion/knowii/commit/a47e2adfbd671b06be7ec4b7d81f9b4efe955b73)), closes [#266](https://github.com/DeveloPassion/knowii/issues/266)
* **code:** fixed community creation bug with the slug generation ([d5284f7](https://github.com/DeveloPassion/knowii/commit/d5284f75270d2a87ad1ee5bb8520e2da13bca3e4))
* **code:** fixed issue with the middleware ([e9b2fda](https://github.com/DeveloPassion/knowii/commit/e9b2fdabfe3af8f9eaf198803a84fc2da4093cd1))
* **code:** fixed issue with the prisma schema. Removed invalid zod annotation ([437f4c3](https://github.com/DeveloPassion/knowii/commit/437f4c3aa23506d2cb68c3710782a342cf1d4f78))
* **deps:** fixed issue with local supabase ([1ed3983](https://github.com/DeveloPassion/knowii/commit/1ed3983c3cab86f5ed0409e2867e5f2f60c7a94d)), closes [#497](https://github.com/DeveloPassion/knowii/issues/497)


### Features

* **all:** added back ping api endpoint ([b254759](https://github.com/DeveloPassion/knowii/commit/b25475900e0174a91df9db824c4f8e1c3cdc50cc))
* **all:** added back version api endpoint ([269bf2a](https://github.com/DeveloPassion/knowii/commit/269bf2af884e58f59cf97ef7ff585d63fffbd93d))
* **all:** added GH issue and PR templates ([638bbc8](https://github.com/DeveloPassion/knowii/commit/638bbc84083c6249bb3d7f9d62cd519402a4beab))
* **all:** added logging utility ([b2bfbd6](https://github.com/DeveloPassion/knowii/commit/b2bfbd6b3b708c87877ebee46ce1cceceb875f0b)), closes [#265](https://github.com/DeveloPassion/knowii/issues/265)
* **all:** added new app skeleton ([e817b1f](https://github.com/DeveloPassion/knowii/commit/e817b1f8607e069180b00f9ffe1f614b2cc8a692))
* **all:** implemented the community creation page ([040816d](https://github.com/DeveloPassion/knowii/commit/040816daccf5e70ea699f61da2aa3332175b0f49)), closes [#238](https://github.com/DeveloPassion/knowii/issues/238)
* **all:** introduced DAO layer and improved type safety ([c1422ab](https://github.com/DeveloPassion/knowii/commit/c1422ab6ea7292c28a3c26240a2d87ed820e5c6b))
* **auth:** added new commitlint scopes ([f35cab5](https://github.com/DeveloPassion/knowii/commit/f35cab5486ed54da467d9d3e38da764688b5141e))
* **auth:** added social login buttons to login page ([f7f7b96](https://github.com/DeveloPassion/knowii/commit/f7f7b9669b2afb448de55c4bec19598820628eeb)), closes [#493](https://github.com/DeveloPassion/knowii/issues/493)
* **auth:** added support for FB and Twitter ([b3cca94](https://github.com/DeveloPassion/knowii/commit/b3cca9433d630154f34c8edf6e4851ea23c4fdc8))
* **auth:** implemented means to retrieve the internal user id as part of the supabase session ([5f8969d](https://github.com/DeveloPassion/knowii/commit/5f8969db7b6c2d294be69b605a28989c0498d513))
* **code:** added API endpoint to check for community slug availability ([a58072e](https://github.com/DeveloPassion/knowii/commit/a58072ede829329a582ad69bdf810e00f6a4519f))
* **code:** added utility function to create slugs ([5417bd0](https://github.com/DeveloPassion/knowii/commit/5417bd0332f2686fc593180f6f91319092bf0e32))
* **code:** added zustand for state management together with a first example ([274171f](https://github.com/DeveloPassion/knowii/commit/274171f5ef6d5712035d75d03f94f334b0b58b74)), closes [#538](https://github.com/DeveloPassion/knowii/issues/538) [#538](https://github.com/DeveloPassion/knowii/issues/538)
* **code:** created the community page ([2222c23](https://github.com/DeveloPassion/knowii/commit/2222c2364907da6f83c9350e8c76f5bdc91d6e1d)), closes [#240](https://github.com/DeveloPassion/knowii/issues/240)
* **code:** implement community creation API ([96f1f55](https://github.com/DeveloPassion/knowii/commit/96f1f553753345aa1dc1fa6f51779d2e617bb004))
* **code:** implemented reusable schemas for API responses and errors ([18167af](https://github.com/DeveloPassion/knowii/commit/18167afadff297595e902d2be2bcb9acc1648bb0)), closes [#506](https://github.com/DeveloPassion/knowii/issues/506)
* **code:** implemented the API to retrieve a community ([5a88912](https://github.com/DeveloPassion/knowii/commit/5a88912f6520362cc0c072d4ca71e486d9e6652f)), closes [#535](https://github.com/DeveloPassion/knowii/issues/535)
* **code:** introduced error categories to distinguish between business, security and technical errors ([159078e](https://github.com/DeveloPassion/knowii/commit/159078ef36eaecf60e2296f46c7e15ed290b30f4)), closes [#515](https://github.com/DeveloPassion/knowii/issues/515)
* **code:** set current user as owner of newly created communities ([b4c6a95](https://github.com/DeveloPassion/knowii/commit/b4c6a95c7fd36d44064b1a90a85d5dd04dbb7c5e)), closes [#528](https://github.com/DeveloPassion/knowii/issues/528)
* **code:** vary the logging levels per environment ([db61c01](https://github.com/DeveloPassion/knowii/commit/db61c01f24db75ad744a842bf9844fbde3db692d)), closes [#524](https://github.com/DeveloPassion/knowii/issues/524)
* **qa:** improved logging details (subcategory + detailed context logging) ([275c3b6](https://github.com/DeveloPassion/knowii/commit/275c3b6e78b02c129fa4ed634a1c5d607b18727e))


### Reverts

* **deps:** reverted update of supabase auth-helpers as those break sign up ([ae06319](https://github.com/DeveloPassion/knowii/commit/ae063191d0ed4db0fa83b743ea582fea5f76c93f)), closes [#533](https://github.com/DeveloPassion/knowii/issues/533)



