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
  * [user-chars](#user-chars)
  * [group-admin](#group-admin)
  * [group-manager](#group-manager)
  * [service-admin](#service-admin)
  * [statistics](#statistics)
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
- The CSRF token to use in POST, PUT and DELETE requests. `GET /user/auth/csrf-token`

Character API
- Return the logged in EVE character. `GET /user/character/show`
- Update a character with data from ESI. `PUT /user/character/{id}/update`

Group API
- List all public groups that the player can join. `GET /user/group/public`

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
- List all logins. `GET /user/settings/eve-login/list`

Service API
- Returns service. `GET /user/service/{id}/get`
- Returns all player's service accounts for a service. `GET /user/service/{id}/accounts`
- Registers a new account with a service. `POST /user/service/{id}/register`
- Update an account. `PUT /user/service/{id}/update-account/{characterId}`
- Resets password for one account. `PUT /user/service/{id}/reset-password/{characterId}`

#### user-admin

Allows a player to add and remove roles from players.

Character API
- Returns a list of characters (together with the name of the player account/main character)
                    that matches the name (partial matching). `GET /user/character/find-character/{name}`

Player API
- List all players with characters. `GET /user/player/with-characters`
- List all players without characters. `GET /user/player/without-characters`
- Check whether groups for this account are disabled or will be disabled soon. `GET /user/player/{id}/groups-disabled`
- Delete a character. `DELETE /user/player/delete-character/{id}`
- Add a role to the player. `PUT /user/player/{id}/add-role/{name}`
- Remove a role from a player. `PUT /user/player/{id}/remove-role/{name}`
- Show all data from a player. `GET /user/player/{id}/show`
- List all players with a role. `GET /user/player/with-role/{name}`
- Lists all players with characters who have a certain status. `GET /user/player/with-status/{name}`

Service API
- Update all service accounts of one player. `PUT /user/service/update-all-accounts/{playerId}`

#### user-manager

Allows a player to add and remove groups from players with "managed" status.

Character API
- Returns a list of characters (together with the name of the player account/main character)
                    that matches the name (partial matching). `GET /user/character/find-character/{name}`

Group API
- List all groups. `GET /user/group/all`
- Adds a player to a group. `PUT /user/group/{id}/add-member/{pid}`
- Remove player from a group. `PUT /user/group/{id}/remove-member/{pid}`

Player API
- Change the player's account status. `PUT /user/player/{id}/set-status/{status}`
- Show all data from a player. `GET /user/player/{id}/show`
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`
- Lists all players with characters who have a certain status. `GET /user/player/with-status/{name}`

Service API
- Update all service accounts of one player. `PUT /user/service/update-all-accounts/{playerId}`

#### user-chars

Allows a player to view all characters of an account.

Character API
- Returns a list of characters (together with the name of the player account/main character)
                    that matches the name (partial matching). `GET /user/character/find-character/{name}`

Player API
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`
- Accepts a list of character names and returns them grouped by account. `POST /user/player/group-characters-by-account`

Service API
- Update all service accounts of one player. `PUT /user/service/update-all-accounts/{playerId}`

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
- Update group description. `PUT /user/group/{id}/update-description`
- Change visibility of a group. `PUT /user/group/{id}/set-visibility/{choice}`
- Change the auto-accept setting of a group. `PUT /user/group/{id}/set-auto-accept/{choice}`
- Change the is-default setting of a group. `PUT /user/group/{id}/set-is-default/{choice}`
- Delete a group. `DELETE /user/group/{id}/delete`
- List all managers of a group. `GET /user/group/{id}/managers`
- List all corporations of a group. `GET /user/group/{id}/corporations`
- List all alliances of a group. `GET /user/group/{id}/alliances`
- List all required groups of a group. `GET /user/group/{id}/required-groups`
- Add required group to a group. `PUT /user/group/{id}/add-required/{groupId}`
- Remove required group from a group. `PUT /user/group/{id}/remove-required/{groupId}`
- List all forbidden groups of a group. `GET /user/group/{id}/forbidden-groups`
- Add forbidden group to a group. `PUT /user/group/{id}/add-forbidden/{groupId}`
- Remove forbidden group from a group. `PUT /user/group/{id}/remove-forbidden/{groupId}`
- Assign a player as manager to a group. `PUT /user/group/{id}/add-manager/{pid}`
- Remove a manager (player) from a group. `PUT /user/group/{id}/remove-manager/{pid}`
- List all members of a group. `GET /user/group/{id}/members`

Player API
- List all players with the role group-manger. `GET /user/player/group-managers`
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`

Service API
- Update all service accounts of one player. `PUT /user/service/update-all-accounts/{playerId}`

#### group-manager

Allows a player to add and remove members to his groups.  
This role is assigned automatically depending on whether the player is a manager of a group.

Character API
- Return a list of players that matches the main character name (partial matching). `GET /user/character/find-player/{name}`

Group API
- List all managers of a group. `GET /user/group/{id}/managers`
- List all required groups of a group. `GET /user/group/{id}/required-groups`
- List all forbidden groups of a group. `GET /user/group/{id}/forbidden-groups`
- List all applications of a group. `GET /user/group/{id}/applications`
- Accept a player's request to join a group. `PUT /user/group/accept-application/{id}`
- Deny a player's request to join a group. `PUT /user/group/deny-application/{id}`
- Adds a player to a group. `PUT /user/group/{id}/add-member/{pid}`
- Remove player from a group. `PUT /user/group/{id}/remove-member/{pid}`
- List all members of a group. `GET /user/group/{id}/members`

#### service-admin

Allows players to create and edit services.

ServiceAdmin API
- List all services. `GET /user/service-admin/list`
- Create a service. `POST /user/service-admin/create`
- Rename a service. `PUT /user/service-admin/{id}/rename`
- Delete a service. `DELETE /user/service-admin/{id}/delete`
- Save the service configuration. `PUT /user/service-admin/{id}/save-configuration`

#### statistics

Allows players to view statistics.

Statistics API
- Returns player login numbers, max. last 13 months. `GET /user/statistics/player-logins`
- Returns total monthly app request numbers, max. last 13 entries. `GET /user/statistics/total-monthly-app-requests`
- Returns monthly app request numbers. `GET /user/statistics/monthly-app-requests`
- Returns total daily app request numbers. `GET /user/statistics/total-daily-app-requests`

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
- Add an EVE login to an app. `PUT /user/app/{id}/add-eve-login/{eveLoginId}`
- Remove an EVE login from an app. `PUT /user/app/{id}/remove-eve-login/{eveLoginId}`

Group API
- List all groups. `GET /user/group/all`

Player API
- List all players with the role app-manger. `GET /user/player/app-managers`
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`

Service API
- Update all service accounts of one player. `PUT /user/service/update-all-accounts/{playerId}`

#### app-manager

Allows a player to change the secret of his apps.  
This role is assigned automatically depending on whether the player is a manager of an app.

App API
- Shows app information. `GET /user/app/{id}/show`
- Generates a new application secret. The new secret is returned, it cannot be retrieved afterwards. `PUT /user/app/{id}/change-secret`

#### esi

Allows a player to make an ESI request on behalf of a character from the database.

ESI API
- ESI request. `GET /user/esi/request`
- Same as GET /user/esi/request, but for POST requests. `POST /user/esi/request`

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
- Update login. `PUT /user/settings/eve-login`
- Create a new login. `POST /user/settings/eve-login/{name}`
- Delete login. `DELETE /user/settings/eve-login/{id}`
- List in-game roles (without HQ, base and other 'Hangar Access' and 'Container Access' roles). `GET /user/settings/eve-login/roles`

#### tracking

Allows a player to view corporation member tracking data.  
In addition, membership in a group that determines which company is visible is necessary.  
This role is assigned automatically based on group membership.

Corporation API
- Returns corporations that have member tracking data. `GET /user/corporation/tracked-corporations`
- Returns tracking data of corporation members. `GET /user/corporation/{id}/members`

Player API
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`

#### tracking-admin

Allows a player to change the tracking corporation/groups configuration.

Corporation API
- Returns a list of directors with an ESI token for this corporation. `GET /user/corporation/{id}/tracking-director`
- Returns required groups to view member tracking data. `GET /user/corporation/{id}/get-groups-tracking`
- Add a group to the corporation for member tracking permission. `PUT /user/corporation/{id}/add-group-tracking/{groupId}`
- Remove a group for member tracking permission from the corporation. `PUT /user/corporation/{id}/remove-group-tracking/{groupId}`
- Returns all corporations that have member tracking data. `GET /user/corporation/all-tracked-corporations`

#### watchlist

Allows players to view the watchlist if they are also member of an appropriate group.  
This role is assigned automatically based on group membership.

Player API
- Show player with characters, moved characters, groups and service accounts. `GET /user/player/{id}/characters`

Watchlist API
- Lists all watchlists with view permission. `GET /user/watchlist/list-available`
- List of player accounts that have characters in one of the configured alliances or corporations
                    and additionally have other characters in another player (not NPC) corporation that is not
                    on the allowlist and have not been manually excluded. `GET /user/watchlist/{id}/players`
- Accounts from the watchlist with members in one of the alliances or corporations
                    from the kicklist. `GET /user/watchlist/{id}/players-kicklist`
- List of exempt players. `GET /user/watchlist/{id}/exemption/list`
- List of corporations for this list. `GET /user/watchlist/{id}/corporation/list`
- List of alliances for this list. `GET /user/watchlist/{id}/alliance/list`
- List of corporations for the kicklist. `GET /user/watchlist/{id}/kicklist-corporation/list`
- List of alliances for the kicklist. `GET /user/watchlist/{id}/kicklist-alliance/list`
- List of corporations for the corporation allowlist. `GET /user/watchlist/{id}/allowlist-corporation/list`
- List of alliances for the alliance allowlist. `GET /user/watchlist/{id}/allowlist-alliance/list`

#### watchlist-manager

Allows a player to edit exemptions and settings of a watch list to which they have access.  
This role is assigned automatically based on group membership.

Alliance API
- List all alliances. `GET /user/alliance/all`
- Add an EVE alliance to the database. `POST /user/alliance/add/{id}`

Corporation API
- List all corporations. `GET /user/corporation/all`
- Add an EVE corporation to the database. `POST /user/corporation/add/{id}`

Watchlist API
- Lists all watchlists with manage permission. `GET /user/watchlist/list-available-manage`
- List of exempt players. `GET /user/watchlist/{id}/exemption/list`
- Add player to exemption list. `PUT /user/watchlist/{id}/exemption/add/{player}`
- Remove player from exemption list. `PUT /user/watchlist/{id}/exemption/remove/{player}`
- List of corporations for this list. `GET /user/watchlist/{id}/corporation/list`
- Add corporation to the list. `PUT /user/watchlist/{id}/corporation/add/{corporation}`
- Remove corporation from the list. `PUT /user/watchlist/{id}/corporation/remove/{corporation}`
- List of alliances for this list. `GET /user/watchlist/{id}/alliance/list`
- Add alliance to the list. `PUT /user/watchlist/{id}/alliance/add/{alliance}`
- Remove alliance from the list. `PUT /user/watchlist/{id}/alliance/remove/{alliance}`
- List of corporations for the kicklist. `GET /user/watchlist/{id}/kicklist-corporation/list`
- Add corporation to the kicklist. `PUT /user/watchlist/{id}/kicklist-corporation/add/{corporation}`
- Remove corporation from the kicklist. `PUT /user/watchlist/{id}/kicklist-corporation/remove/{corporation}`
- List of alliances for the kicklist. `GET /user/watchlist/{id}/kicklist-alliance/list`
- Add alliance to the kicklist. `PUT /user/watchlist/{id}/kicklist-alliance/add/{alliance}`
- Remove alliance from the kicklist. `PUT /user/watchlist/{id}/kicklist-alliance/remove/{alliance}`
- List of corporations for the corporation allowlist. `GET /user/watchlist/{id}/allowlist-corporation/list`
- Add corporation to the corporation allowlist. `PUT /user/watchlist/{id}/allowlist-corporation/add/{corporation}`
- Remove corporation from the corporation allowlist. `PUT /user/watchlist/{id}/allowlist-corporation/remove/{corporation}`
- List of alliances for the alliance allowlist. `GET /user/watchlist/{id}/allowlist-alliance/list`
- Add alliance to the alliance allowlist. `PUT /user/watchlist/{id}/allowlist-alliance/add/{alliance}`
- Remove alliance from the alliance allowlist. `PUT /user/watchlist/{id}/allowlist-alliance/remove/{alliance}`

#### watchlist-admin

Allows a player to edit watchlist access.

Group API
- List all groups. `GET /user/group/all`

Watchlist API
- Create a watchlist. `POST /user/watchlist/create`
- Rename a watchlist. `PUT /user/watchlist/{id}/rename`
- Delete a watchlist. `DELETE /user/watchlist/{id}/delete`
- Lock or unlock the watchlist settings. `PUT /user/watchlist/{id}/lock-watchlist-settings/{lock}`
- Lists all watchlists. `GET /user/watchlist/listAll`
- List of corporations for this list. `GET /user/watchlist/{id}/corporation/list`
- Add corporation to the list. `PUT /user/watchlist/{id}/corporation/add/{corporation}`
- Remove corporation from the list. `PUT /user/watchlist/{id}/corporation/remove/{corporation}`
- List of alliances for this list. `GET /user/watchlist/{id}/alliance/list`
- Add alliance to the list. `PUT /user/watchlist/{id}/alliance/add/{alliance}`
- Remove alliance from the list. `PUT /user/watchlist/{id}/alliance/remove/{alliance}`
- List of groups with access to this list. `GET /user/watchlist/{id}/group/list`
- Add access group to the list. `PUT /user/watchlist/{id}/group/add/{group}`
- Remove access group from the list. `PUT /user/watchlist/{id}/group/remove/{group}`
- List of groups with manager access to this list. `GET /user/watchlist/{id}/manager-group/list`
- Add manager access group to the list. `PUT /user/watchlist/{id}/manager-group/add/{group}`
- Remove manager access group from the list. `PUT /user/watchlist/{id}/manager-group/remove/{group}`

### Application API

#### app

This role is added to all authenticated apps automatically. It
cannot be added to player accounts.

Application API
- Show app information. `GET /app/v1/show`

#### app-groups

Allows an app to get groups from a player account.

Application - Groups API
- Return groups of the character's player account. `GET /app/v2/groups/{cid}`
- Return groups of multiple players, identified by one of their character IDs. `POST /app/v1/groups`
- Return groups of the corporation. `GET /app/v2/corp-groups/{cid}`
- Return groups of multiple corporations. `POST /app/v1/corp-groups`
- Return groups of the alliance. `GET /app/v2/alliance-groups/{aid}`
- Return groups of multiple alliances. `POST /app/v1/alliance-groups`
- Returns groups from the character's account, if available, or the corporation and alliance. `GET /app/v1/groups-with-fallback`
- Returns the main character IDs from all group members. `GET /app/v1/group-members/{groupId}`

#### app-chars

Allows an app to get characters from a player account.

Application - Characters API
- Returns the main character of the player account to which the character ID belongs. `GET /app/v2/main/{cid}`
- Returns the player account to which the character ID belongs. `GET /app/v1/player/{characterId}`
- Returns all characters of the player account to which the character ID belongs. `GET /app/v1/characters/{characterId}`
- Returns all characters from multiple player accounts identified by character IDs. `POST /app/v1/characters`
- Returns all known characters from the parameter list. `POST /app/v1/character-list`
- Returns all characters from the player account. `GET /app/v1/player-chars/{playerId}`
- Returns the player account to which the character ID belongs with all characters. `GET /app/v1/player-with-characters/{characterId}`
- Returns all characters that were removed from the player account to which the character ID
                    belongs. `GET /app/v1/removed-characters/{characterId}`
- Returns all characters that were moved from another account to the player account to which the
                    ID belongs. `GET /app/v1/incoming-characters/{characterId}`
- Returns a list of all players that have a character in the corporation. `GET /app/v1/corp-players/{corporationId}`
- Returns a list of all known characters from the corporation. `GET /app/v1/corp-characters/{corporationId}`

#### app-tracking

Allows an app to get corporation member tracking data.

Application - Tracking API
- Return corporation member tracking data. `GET /app/v1/corporation/{id}/member-tracking`

#### app-esi

Allows an app to make an ESI request on behalf of a character from the database.

Application API
- Returns character IDs of characters that have a valid ESI token of the specified EVE login.
  `GET /app/v1/esi/eve-login/{name}/characters`
- Makes an ESI GET or POST request on behalf on an EVE character and returns the result. `/app/v1/esi`  
  This endpoint can also be used with OpenAPI clients generated for ESI,
  see [app-esi-examples.php](app-esi-examples.php) for more.
