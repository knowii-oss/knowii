# TODO

- Create BusinessException abstract class
- Throw BusinessException if community name is already taken
- Add boolean flag to form for the private/public community
- Add react key to community list entries
- Remove uses of Knowii::newCommunityModel()
- Places to update when modifying the Community model
  - PHP
    - Migration file
    - Community model
    - CreateCommunity implements CreatesCommunities
    - CommunityController (inertia)
    - CommunityApiController
    - Factories
  - Frontend
    - communities.intf.ts
    - Community screens
- Refactor CommunityController (inertia)
-
