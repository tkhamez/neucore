# API

All API endpoints from the backend are protected by roles.

The API is documented with OpenAPI, it is available at
[https://[domain]/api.html](https://neucore.herokuapp.com/api.html).

## Roles Overview

<!-- toc -->

### User API

#### anonymous

This role is added automatically to every unauthenticated client (for `/api/user` endpoints, not apps),
it cannot be added to player accounts.

{anonymous}

#### user

This role is added to all player accounts.

{user}

#### user-admin

Allows a player to add and remove roles from players.

{user-admin}

#### user-manager

Allows a player to add and remove groups from players with "managed" status.

{user-manager}

#### user-chars

Allows a player to view all characters of an account.

{user-chars}

#### group-admin

Allows a player to create groups and add and remove managers or corporation and alliances.

{group-admin}

#### group-manager

Allows a player to add and remove members to his groups.  
This role is assigned automatically depending on whether the player is a manager of a group.

{group-manager}

#### service-admin

Allows players to create and edit services.

{service-admin}

#### statistics

Allows players to view statistics.

{statistics}

#### app-admin

Allows a player to create apps and add and remove managers and roles.

{app-admin}

#### app-manager

Allows a player to change the secret of his apps.  
This role is assigned automatically depending on whether the player is a manager of an app.

{app-manager}

#### esi

Allows a player to make an ESI request on behalf of a character from the database.

{esi}

#### settings

Allows a player to change the system settings.

{settings}

#### tracking

Allows a player to view corporation member tracking data.  
In addition, membership in a group that determines which company is visible is necessary.  
This role is assigned automatically based on group membership.

{tracking}

#### tracking-admin

Allows a player to change the tracking corporation/groups configuration.

{tracking-admin}

#### watchlist

Allows players to view the watchlist if they are also member of an appropriate group.  
This role is assigned automatically based on group membership.

{watchlist}

#### watchlist-manager

Allows a player to edit exemptions and settings of a watch list to which they have access.  
This role is assigned automatically based on group membership.

{watchlist-manager}

#### watchlist-admin

Allows a player to edit watchlist access.

{watchlist-admin}

### Application API

#### app

This role is added to all authenticated apps automatically. It
cannot be added to player accounts.

{app}

#### app-groups

Allows an app to get groups from a player account.

{app-groups}

#### app-chars

Allows an app to get characters from a player account.

{app-chars}

#### app-tracking

Allows an app to get corporation member tracking data.

{app-tracking}

#### app-esi

Allows an app to make an ESI request on behalf of a character from the database.

Application API
- Returns character IDs of characters that have a valid ESI token of the specified EVE login.
  `GET /app/v1/esi/eve-login/{name}/characters`
- Makes an ESI GET or POST request on behalf on an EVE character and returns the result. `/app/v2/esi`  
  This endpoint can also be used with OpenAPI clients generated for ESI,
  see [app-esi-examples.php](app-esi-examples.php) for more.
