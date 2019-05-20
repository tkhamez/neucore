# API

All API endpoints from the backend are protected by roles.

The API is documented with OpenAPI, it is available at `https://[domain]/openapi-3.yaml`.

There is also an OpenAPI definition file available that contains only the API for applications at
[https://[domain]/application-api-3.yml](https://neucore.herokuapp.com/application-api-3.yml).

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

#### group-admin

Allows a player to create groups and add and remove managers or corporation and alliances.

{group-admin}

#### group-manager

Allows a player to add and remove members to his groups.

{group-manager}

#### app-admin

Allows a player to create apps and add and remove managers and roles.

{app-admin}

#### app-manager

Allows a player to change the secret of his apps.

{app-manager}

#### esi

Allows a player to make an ESI request on behalf of a character from the database.

{esi}

#### settings

Allows a player to change the system settings.

{settings}

#### tracking

Allows a player to view corporation member tracking data.

{tracking}

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
- Makes an ESI GET or POST request on behalf on an EVE character and returns the result. `/app/v1/esi`
  This endpoint can also be used with OpenAPI clients generated for ESI,
  see [app-esi-examples.php](app-esi-examples.php) for more.
