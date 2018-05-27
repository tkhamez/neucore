# Features

This primarily describes the back-end, most functions is not yet available
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

- Can login with EVE SSO.
- This role is added automatically to every unauthenticated client.
- This role cannot be added to player accounts.

### user

- This role is added to all player accounts.
- Can see his own data (character name, groups etc.).
- Can see a list of his EVE characters.
- Can update his characters from ESI.
- Can change his "Main".
- Can add "Alts". Alts need to be authenticated with EVE SSO before they are added to the player account.
- Can see a list of public groups.
- Can request to be added to a public group.
- Can see a list of his applications.
- Can cancel an application.
- Can leave a group.
- Can logout.

### user-admin

- Can see a list of all player accounts.
- Can see all data (characters, roles, groups, etc.) from all players.
- Can update any character from ESI.
- Can add and remove from player accounts.
- Can search for characters by character name.
- Can search for players by character ID.

### group-admin

- Can see a list of all groups.
- Can create, rename and delete groups.
- Can change the visibility of a group.
- Can see a list of all player accounts with the role group-manager.
- Can add and remove group managers to a group.
- Can see a list of all managers of a group.
- Can manage configuration for automatic group assignment ("Corporation" and "Alliance" API).
- Can see a list of all player accounts.
- Can search for characters by character name.
- Can search for players by character ID.

### group-manager

- Can see groups of which he is manager.
- Can see a list of member from his groups.
- Can see players that requested to be added to his groups
- Can add and remove any player to a group of which he is manager.
- Can delete a player's request to join a group of which he is manager.
- Can see a list of all players.

### app-admin

- Can see a list of all apps.
- Can create, rename and delete apps.
- Can see a list of all groups.
- Can see a list of all groups of an app
- Can add and remove apps to/from a group.
- Can see a list of all player accounts with the role app-manager.
- Can see a list of all managers of an app.
- Can assign app managers to an app.

### app-manager

- Can change the password for apps of which he is manager.

### app

- This role is added to all authenticated apps automatically.
- This role cannot be added to player accounts.
- Can request info about itself (name, ID)
- Can request groups for a character ("Alt" or "Main" does not matter). The API only returns
  groups that are assigned to the app and the associated player account of the requested character.
- Can query the main character of an account using an EVE character ID.
- Can request groups of corporations and alliances.

## Console application

The console application has commands to:
- update characters with information from ESI, like corporation etc. and checks ESI tokens.
- perform automatic group assignment based on corporation and alliance to group configuration.

