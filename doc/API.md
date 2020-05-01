# API

All API endpoints from the backend are protected by roles.

The API is documented with OpenAPI, it is available at
[https://[domain]/api.html](https://neucore.herokuapp.com/api.html).

## Roles Overview

<!-- toc -->

- [User API](#user-api)
  * [anonymous](#anonymous)
  * [user](#user)
  * [user-admin](#user-admin)
  * [user-manager](#user-manager)
  * [group-admin](#group-admin)
  * [group-manager](#group-manager)
  * [app-admin](#app-admin)
  * [app-manager](#app-manager)
  * [esi](#esi)
  * [settings](#settings)
  * [tracking](#tracking)
  * [tracking-admin](#tracking-admin)
  * [watchlist](#watchlist)
  * [watchlist-manager](#watchlist-manager)
  * [watchlist-admin](#watchlist-admin)
- [Application API](#application-api)
  * [app](#app)
  * [app-groups](#app-groups)
  * [app-chars](#app-chars)
  * [app-tracking](#app-tracking)
  * [app-esi](#app-esi)

<!-- tocstop -->

### User API

#### anonymous

This role is added automatically to every unauthenticated client (for `/api/user` endpoints, not apps),
it cannot be added to player accounts.

Auth API
- Result of last SSO attempt. `GET /user/auth/result`

Settings API
- List all settings. `GET /user/settings/system/list`

#### user

This role is added to all player accounts.

Auth API
- Result of last SSO attempt. `GET /user/auth/result`
- User logout. `POST /user/auth/logout`

Character API
- Return the logged in EVE character. `GET /user/character/show`
- Update a character with data from ESI. `PUT /user/character/{id}/update`

Group API
- List all public groups. `GET /user/group/public`

Player API
- Return the logged in player with all properties. `GET /user/player/show`
- Check whether groups for this account are disabled or will be disabled soon. `GET /user/player/groups-disabled`
- Submit a group application. `PUT /user/player/add-application/{gid}`
- Cancel a group application. `PUT /user/player/remove-application/{gid}`
- Show all group applications. `GET /user/player/show-applications`
- Leave a group. `PUT /user/player/leave-group/{gid}`
- Change the main character from the player account. `PUT /user/player/set-main/{cid}`
- Delete a character. `DELETE /user/player/delete-character/{id}`

Settings API
- List all settings. `GET /user/settings/system/list`

#### user-admin

Allows a player to add and remove roles from players.

Character API
- Return a list of characters that matches the name (partial matching). `GET /user/character/find-by/{name}`

Player API
- List all players with characters. `GET /user/player/with-characters`
- List all players without characters. `GET /user/player/without-characters`
- List all players with a character with an invalid token. `GET /user/player/invalid-token`
- List all players with a character with no token. `GET /user/player/no-token`
- Check whether groups for this account are disabled or will be disabled soon. `GET /user/player/{id}/groups-disabled`
- Delete a character. `DELETE /user/player/delete-character/{id}`
- Add a role to the player. `PUT /user/player/{id}/add-role/{name}`
- Remove a role from a player. `PUT /user/player/{id}/remove-role/{name}`
- Show all data from a player. `GET /user/player/{id}/show`
- List all players with a role. `GET /user/player/with-role/{name}`
- Lists all players with characters who have a certain status. `GET /user/player/with-status/{name}`

#### user-manager

Allows a player to add and remove groups from players with "managed" status.

Character API
- Return a list of characters that matches the name (partial matching). `GET /user/character/find-by/{name}`

Group API
- List all groups. `GET /user/group/all`
- Adds a player to a group. `PUT /user/group/{id}/add-member/{pid}`
- Remove player from a group. `PUT /user/group/{id}/remove-member/{pid}`

Player API
- Change the player's account status. `PUT /user/player/{id}/set-status/{status}`
- Show all data from a player. `GET /user/player/{id}/show`
- Show player with characters. `GET /user/player/{id}/characters`
- Lists all players with characters who have a certain status. `GET /user/player/with-status/{name}`

#### group-admin

Allows a player to create groups and add and remove managers or corporation and alliances.

Alliance API
- List all alliances. `GET /user/alliance/all`
- List all alliances that have groups assigned. `GET /user/alliance/with-groups`
- Add an EVE alliance to the database. `POST /user/alliance/add/{id}`
- Add a group to the alliance. `PUT /user/alliance/{id}/add-group/{gid}`
- Remove a group from the alliance. `PUT /user/alliance/{id}/remove-group/{gid}`

Corporation API
- List all corporations. `GET /user/corporation/all`
- List all corporations that have groups assigned. `GET /user/corporation/with-groups`
- Add an EVE corporation to the database. `POST /user/corporation/add/{id}`
- Add a group to the corporation. `PUT /user/corporation/{id}/add-group/{gid}`
- Remove a group from the corporation. `PUT /user/corporation/{id}/remove-group/{gid}`

Group API
- List all groups. `GET /user/group/all`
- Create a group. `POST /user/group/create`
- Rename a group. `PUT /user/group/{id}/rename`
- Change visibility of a group. `PUT /user/group/{id}/set-visibility/{choice}`
- Delete a group. `DELETE /user/group/{id}/delete`
- List all managers of a group. `GET /user/group/{id}/managers`
- List all corporations of a group. `GET /user/group/{id}/corporations`
- List all alliances of a group. `GET /user/group/{id}/alliances`
- List all required groups of a group. `GET /user/group/{id}/required-groups`
- Add required group to a group. `PUT /user/group/{id}/add-required/{groupId}`
- Remove required group from a group. `PUT /user/group/{id}/remove-required/{groupId}`
- Assign a player as manager to a group. `PUT /user/group/{id}/add-manager/{pid}`
- Remove a manager (player) from a group. `PUT /user/group/{id}/remove-manager/{pid}`
- List all members of a group. `GET /user/group/{id}/members`

Player API
- List all players with the role group-manger. `GET /user/player/group-managers`
- Show player with characters. `GET /user/player/{id}/characters`

#### group-manager

Allows a player to add and remove members to his groups.

Character API
- Return a list of characters that matches the name (partial matching). `GET /user/character/find-by/{name}`

Group API
- List all required groups of a group. `GET /user/group/{id}/required-groups`
- List all applications of a group. `GET /user/group/{id}/applications`
- Accept a player's request to join a group. `PUT /user/group/accept-application/{id}`
- Deny a player's request to join a group. `PUT /user/group/deny-application/{id}`
- Adds a player to a group. `PUT /user/group/{id}/add-member/{pid}`
- Remove player from a group. `PUT /user/group/{id}/remove-member/{pid}`
- List all members of a group. `GET /user/group/{id}/members`

#### app-admin

Allows a player to create apps and add and remove managers and roles.

App API
- List all apps. `GET /user/app/all`
- Create an app. `POST /user/app/create`
- Shows app information. `GET /user/app/{id}/show`
- Rename an app. `PUT /user/app/{id}/rename`
- Delete an app. `DELETE /user/app/{id}/delete`
- Add a group to an app. `PUT /user/app/{id}/add-group/{gid}`
- Remove a group from an app. `PUT /user/app/{id}/remove-group/{gid}`
- List all managers of an app. `GET /user/app/{id}/managers`
- Assign a player as manager to an app. `PUT /user/app/{id}/add-manager/{pid}`
- Remove a manager (player) from an app. `PUT /user/app/{id}/remove-manager/{pid}`
- Add a role to the app. `PUT /user/app/{id}/add-role/{name}`
- Remove a role from an app. `PUT /user/app/{id}/remove-role/{name}`

Group API
- List all groups. `GET /user/group/all`

Player API
- List all players with the role app-manger. `GET /user/player/app-managers`
- Show player with characters. `GET /user/player/{id}/characters`

#### app-manager

Allows a player to change the secret of his apps.

App API
- Shows app information. `GET /user/app/{id}/show`
- Generates a new application secret. The new secret is returned, it cannot be retrieved afterwards. `PUT /user/app/{id}/change-secret`

#### esi

Allows a player to make an ESI request on behalf of a character from the database.

ESI API
- ESI request. `GET /user/esi/request`

#### settings

Allows a player to change the system settings.

Alliance API
- List all alliances. `GET /user/alliance/all`

Corporation API
- List all corporations. `GET /user/corporation/all`

Settings API
- Change a system settings variable. `PUT /user/settings/system/change/{name}`
- Sends a 'invalid ESI token' test mail to the logged-in character. `POST /user/settings/system/send-invalid-token-mail`
- Sends a 'missing character' test mail to the logged-in character. `POST /user/settings/system/send-missing-character-mail`
- Validates ESI token from a director and updates name and corporation. `PUT /user/settings/system/validate-director/{name}`

#### tracking

Allows a player to view corporation member tracking data.  
In addition, membership in a group that determines which company is visible is necessary.  
This role is assigned automatically based on group membership.

Corporation API
- Returns corporations that have member tracking data. `GET /user/corporation/tracked-corporations`
- Returns tracking data of corporation members. `GET /user/corporation/{id}/members`

Player API
- Show player with characters. `GET /user/player/{id}/characters`

#### tracking-admin

Allows a player to change the tracking corporation/groups configuration.

Corporation API
- Returns required groups to view member tracking data. `GET /user/corporation/{id}/get-groups-tracking`
- Add a group to the corporation for member tracking permission. `PUT /user/corporation/{id}/add-group-tracking/{groupId}`
- Remove a group for member tracking permission from the corporation. `PUT /user/corporation/{id}/remove-group-tracking/{groupId}`
- Returns corporations that have member tracking data. `GET /user/corporation/tracked-corporations`

#### watchlist

Allows players to view the watchlist if they are also member of an appropriate group.  
This role is assigned automatically based on group membership.

Character API
- Return a list of characters that matches the name (partial matching). `GET /user/character/find-by/{name}`

Player API
- Show player with characters. `GET /user/player/{id}/characters`

Watchlist API
- List of player accounts that have characters in one of the configured alliances or corporations
                    and additionally have other characters in another player (not NPC) corporation that is not
                    whitelisted and have not been manually excluded. `GET /user/watchlist/{id}/players`
- Accounts from the watchlist with members in one of the blacklisted alliances or corporations. `GET /user/watchlist/{id}/players-blacklist`
- List of exempt players. `GET /user/watchlist/{id}/exemption/list`
- List of corporations for this list. `GET /user/watchlist/{id}/corporation/list`
- List of alliances for this list. `GET /user/watchlist/{id}/alliance/list`
- List of corporations for the blacklist. `GET /user/watchlist/{id}/blacklist-corporation/list`
- List of alliances for the blacklist. `GET /user/watchlist/{id}/blacklist-alliance/list`
- List of corporations for the corporation whitelist. `GET /user/watchlist/{id}/whitelist-corporation/list`
- List of alliances for the alliance whitelist. `GET /user/watchlist/{id}/whitelist-alliance/list`

#### watchlist-manager

Allows a player to edit exemptions and settings of a watch list to which they have access.

Alliance API
- List all alliances. `GET /user/alliance/all`
- Add an EVE alliance to the database. `POST /user/alliance/add/{id}`

Corporation API
- List all corporations. `GET /user/corporation/all`
- Add an EVE corporation to the database. `POST /user/corporation/add/{id}`

Watchlist API
- List of exempt players. `GET /user/watchlist/{id}/exemption/list`
- Add player to exemption list. `PUT /user/watchlist/{id}/exemption/add/{player}`
- Remove player from exemption list. `PUT /user/watchlist/{id}/exemption/remove/{player}`
- List of corporations for this list. `GET /user/watchlist/{id}/corporation/list`
- Add corporation to the list. `PUT /user/watchlist/{id}/corporation/add/{corporation}`
- Remove corporation from the list. `PUT /user/watchlist/{id}/corporation/remove/{corporation}`
- List of alliances for this list. `GET /user/watchlist/{id}/alliance/list`
- Add alliance to the list. `PUT /user/watchlist/{id}/alliance/add/{alliance}`
- Remove alliance from the list. `PUT /user/watchlist/{id}/alliance/remove/{alliance}`
- List of corporations for the blacklist. `GET /user/watchlist/{id}/blacklist-corporation/list`
- Add corporation to the blacklist. `PUT /user/watchlist/{id}/blacklist-corporation/add/{corporation}`
- Remove corporation from the blacklist. `PUT /user/watchlist/{id}/blacklist-corporation/remove/{corporation}`
- List of alliances for the blacklist. `GET /user/watchlist/{id}/blacklist-alliance/list`
- Add alliance to the blacklist. `PUT /user/watchlist/{id}/blacklist-alliance/add/{alliance}`
- Remove alliance from the blacklist. `PUT /user/watchlist/{id}/blacklist-alliance/remove/{alliance}`
- List of corporations for the corporation whitelist. `GET /user/watchlist/{id}/whitelist-corporation/list`
- Add corporation to the corporation whitelist. `PUT /user/watchlist/{id}/whitelist-corporation/add/{corporation}`
- Remove corporation from the corporation whitelist. `PUT /user/watchlist/{id}/whitelist-corporation/remove/{corporation}`
- List of alliances for the alliance whitelist. `GET /user/watchlist/{id}/whitelist-alliance/list`
- Add alliance to the alliance whitelist. `PUT /user/watchlist/{id}/whitelist-alliance/add/{alliance}`
- Remove alliance from the alliance whitelist. `PUT /user/watchlist/{id}/whitelist-alliance/remove/{alliance}`

#### watchlist-admin

Allows a player to edit watchlist access.

Group API
- List all groups. `GET /user/group/all`

Watchlist API
- List of groups with access to this list. `GET /user/watchlist/{id}/group/list`
- Add access group to the list. `PUT /user/watchlist/{id}/group/add/{group}`
- Remove access group from the list. `PUT /user/watchlist/{id}/group/remove/{group}`

### Application API

#### app

This role is added to all authenticated apps automatically. It
cannot be added to player accounts.

Application API
- Show app information. `GET /app/v1/show`

#### app-groups

Allows an app to get groups from a player account.

Application API
- Return groups of the character's player account. `GET /app/v2/groups/{cid}`
- Return groups of multiple players, identified by one of their character IDs. `POST /app/v1/groups`
- Return groups of the corporation. `GET /app/v2/corp-groups/{cid}`
- Return groups of multiple corporations. `POST /app/v1/corp-groups`
- Return groups of the alliance. `GET /app/v2/alliance-groups/{aid}`
- Return groups of multiple alliances. `POST /app/v1/alliance-groups`
- Returns groups from the character's account, if available, or the corporation and alliance. `GET /app/v1/groups-with-fallback`

#### app-chars

Allows an app to get characters from a player account.

Application API
- Return the main character of the player account to which the character ID belongs. `GET /app/v2/main/{cid}`
- Return the player account to which the character ID belongs. `GET /app/v1/player/{characterId}`
- Return all characters of the player account to which the character ID belongs. `GET /app/v1/characters/{characterId}`
- Return all characters from the player account. `GET /app/v1/player-chars/{playerId}`
- Return all characters that were removed from the player account to which the character ID belongs. `GET /app/v1/removed-characters/{characterId}`
- Return all characters that were moved from another account to the player account to which the
                    ID belongs. `GET /app/v1/incoming-characters/{characterId}`
- Return a list of all players that have a character in the corporation. `GET /app/v1/corp-players/{corporationId}`

#### app-tracking

Allows an app to get corporation member tracking data.

Application API
- Return corporation member tracking data. `GET /app/v1/corporation/{id}/member-tracking`

#### app-esi

Allows an app to make an ESI request on behalf of a character from the database.

Application API
- Makes an ESI GET or POST request on behalf on an EVE character and returns the result. `/app/v1/esi`  
  This endpoint can also be used with OpenAPI clients generated for ESI,
  see [app-esi-examples.php](app-esi-examples.php) for more.
