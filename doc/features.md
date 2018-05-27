# Features

This primarily describes the back-end, most functions are not yet available
in the front-end. Admins must use the Swagger-UI for now (`https://[domain]/api.html`).

## Player Accounts and Character Registration

Each EVE character belongs to a player account, an account can have several characters.

When a character logs in via EVE SSO for the first time, a new player account is created
and that character is marked as the main character.

After a successful login, additional characters (alts) can be added to the account. This
is also done via EVE SSO.

If a character to be added to an account already belongs to another account, it will be
removed from that account and added to the current account. This can happen, for example,
if someone has accidentally created two accounts by logging in with an alt that has not
yet been added to the main account.

## Architecture

Backend and Frontend are developed as separated apps, they communicate via an API.
They can run on different domains as long as `[CORS][allow_origin]` is configured accordingly
in `backend/config/settings.php`.

A player logs in with EVE SSO. 3rd party applications authenticate with an HTTP header.

The API is documented with Swagger, it is available at `https://[domain]/swagger.json`.

For more details see the [**front-end**](../frontend/README.md) and [**back-end**](../backend/README.md)
readme.

## Data Structure (Backend)

![Entity–relationship model](er-model.png)

- `players` identifies EVE players. Each player account can have one or more `characters`. One
  character is marked as the "Main" character, the rest are "Alts".
- `apps` are 3rd party applications that have access to the "App" API. They can have several groups.
- A player account can be member of several `groups`.
- A player account can be manager of several groups and apps.
- A player can apply to groups.
- `corporations` and `alliances` can have several groups for automatic group assignments.
- `roles` define what a player or app can do.

## Roles

All API endpoints from the backend are protected by roles.

### anonymous

This role is added automatically to every unauthenticated client, it
cannot be added to player accounts.

Auth API
- EVE SSO login URL. `/user/auth/login-url`
- Result of last SSO attempt. `/user/auth/result`

### user

This role is added to all player accounts.

Auth API
- EVE SSO login URL to add additional characters to an account. `/user/auth/login-alt-url`
- User logout. `/user/auth/logout`

Character API
- Return the logged in EVE character. `/user/character/show`
- Update a character with data from ESI. `/user/character/{id}/update`

Group API
- List all public groups. `/user/group/public`

Player API
- Returns the logged in player with all properties. `/user/player/show`
- Submit a group application. `/user/player/add-application/{gid}`
- Cancel a group application. `/user/player/remove-application/{gid}`
- Leave a group. `/user/player/leave-group/{gid}`
- Change the main character from the player account. `/user/player/set-main/{cid}`

### user-admin

Character API
- Return a list of characters that matches the name (partial matching, minimum 3 characters).
  `/user/character/find-by/{name}`
- Return the player to whom the character belongs. `/user/character/find-player-of/{id}`
- Update a character with data from ESI. `/user/character/{id}/update`

Player API
- List all players. `/user/player/all`
- Add a role to the player. `/user/player/{id}/add-role/{name}`
- Remove a role from a player. `/user/player/{id}/remove-role/{name}`
- Show all data from a player. `/user/player/{id}/show`

### group-admin

Alliance API
- List all alliances. `/user/alliance/all`
- List all alliances that have groups assigned. `/user/alliance/with-groups`
- Add an EVE alliance to the database. `/user/alliance/add/{id}`
- Add a group to the alliance. `/user/alliance/{id}/add-group/{gid}`
- Remove a group from the alliance. `/user/alliance/{id}/remove-group/{gid}`

Character API
- Return a list of characters that matches the name (partial matching, minimum 3 characters).
  `/user/character/find-by/{name}`
- Return the player to whom the character belongs. `/user/character/find-player-of/{id}`

Corporation API
- List all corporations. `/user/corporation/all`
- List all corporations that have groups assigned. `/user/corporation/with-groups`
- Add an EVE corporation to the database. `/user/corporation/add/{id}`
- Add a group to the corporation. `/user/corporation/{id}/add-group/{gid}`
- Remove a group from the corporation. `/user/corporation/{id}/remove-group/{gid}`

Group API
- List all groups. `/user/group/all`
- Create a group. `/user/group/create`
- Rename a group. `/user/group/{id}/rename`
- Change visibility of a group. `/user/group/{id}/set-visibility/{choice}`
- Delete a group. `/user/group/{id}/delete`
- List all managers of a group. `/user/group/{id}/managers`
- Assign a player as manager to a group. `/user/group/{id}/add-manager/{pid}`
- Remove a manager (player) from a group. `/user/group/{id}/remove-manager/{pid}`

Player API
- List all players. `/user/player/all`
- List all players with the role group-manger. `/user/player/group-managers`

### group-manager

Group API
- List all applicants of a group. `/user/group/{id}/applicants`
- Remove a player's request to join a group. `/user/group/{id}/remove-applicant/{pid}`
- Adds a player to a group. `/user/group/{id}/add-member/{pid}`
- Remove player from a group. `/user/group/{id}/remove-member/{pid}`
- List all members of a group. `/user/group/{id}/members`

Player API
- List all players. `/user/player/all`

### app-admin

App API
- List all apps. `/user/app/all`
- Create an app. `/user/app/create`
- Rename an app. `/user/app/{id}/rename`
- Delete an app. `/user/app/{id}/delete`
- List all managers of an app. `/user/app/{id}/managers`
- Assign a player as manager to an app. `/user/app/{id}/add-manager/{pid}`
- Remove a manager (player) from an app. `/user/app/{id}/remove-manager/{pid}`
- List all groups of an app. `/user/app/{id}/groups`
- Add a group to an app. `/user/app/{id}/add-group/{gid}`
- Remove a group from an app. `/user/app/{id}/remove-group/{gid}`

Group API
- List all groups. `/user/group/all`

Player API
- List all players with the role app-manger. `/user/player/app-managers`

### app-manager

App API
- Generates a new application secret. The new secret is returned, it cannot be retrieved afterwards.
  `/user/app/{id}/change-secret`

### app

This role is added to all authenticated apps automatically. It
cannot be added to player accounts.

Application API
- Show app information. `/app/v1/show`
- Return groups of the character's player account. `/app/v1/groups/{cid}`
- Return groups of multiple players, identified by one of their character IDs. `/app/v1/groups`
- Return groups of the corporation. `/app/v1/corp-groups/{cid}`
- Return groups of multiple corporations. `/app/v1/corp-groups`
- Return groups of the alliance. `/app/v1/alliance-groups/{aid}`
- Return groups of multiple alliances. `/app/v1/alliance-groups`
- Returns the main character of the player account to which the character ID belongs.
  `/app/v1/main/{cid}`

## Console application

The console application has commands to:
- update characters with information from ESI, like corporation etc. and checks ESI tokens.
- perform automatic group assignment based on corporation and alliance to group configuration.

