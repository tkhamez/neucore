# Documentation

## Features

* EVE SSO login with configurable permission scopes
* Player accounts with alts
* Role based permission system
* Creation of groups and apps
* Group and app manager for member management
* Automatic group assignment for players based on corporations and alliances from all of their characters
* Optional automatic account deactivation with mail notification when ESI tokens are invalid
* Manually managed accounts that do not require any ESI scopes.
* Corporation member tracking
* CLI commands for data updates from ESI
* An API for applications to query group membership of characters and other data
* ESI request for authorized scopes for any character 
  (via frontend and for apps, see [Examples](app-esi-examples.php))

All API functions are documented with OpenAPI and can be found at `https://[domain]/api.html`.

The frontend is almost complete, only functions related to group applications are missing.

## Authentication of third-party Applications

An application must first be created by an app administrator and assigned to an app manager, 
who can then generate the app secret.

Apps are authenticated using an HTTP authorization header.

The authorization string is composed of the word Bearer followed by a base64-encoded
string containing the app ID and secret separated by a colon (1:my awesome secret).

Example:
```
curl --header "Authorization: Bearer MTpteSBhd2Vzb21lIHNlY3JldA==" https://neucore.tld/api/app/v1/show
```

## Player Accounts

### Character Registration

Each EVE character belongs to a player account, an account can have several characters.

When a character logs in via EVE SSO for the first time, a new player account is created
and that character is marked as the main character.

After a successful login, additional characters (alts) can be added to the account. This
is also done via EVE SSO.

If a character to be added to an account already belongs to another account, it will be
removed from that account and added to the current account. This can happen, for example,
if someone has accidentally created two accounts by logging in with an alt that has not
yet been added to the main account.

### Removing Characters

If an EVE character is deleted or transferred to another EVE account, 
it will also be removed from its current player account.

A player can also manually delete a character if that is enabled in the system setting.

All character removals are recorded and visible to the user admin.

### Account status

There a two account status: standard and managed.

- The status can be changed at any time by a user admin.
- If the status is changed, all groups are removed.
- User admins can manually assign groups to "managed" accounts
  (technically, they can use this API endpoint for all players).
- Automatic group assignment is disabled for managed accounts (but "Required Groups" are still checked, see below).
- Groups are never deactivated for managed accounts.
- There is a separate login URL for managed accounts that does not require ESI scopes (must be allowed in the settings).

## Groups

Visibility
- public: everyone can see them
- private: hidden from non-members
- conditioned: only visible to non-members if they meet 
  certain criteria - not yet implemented

### Automatic Group Assignment

Alliances and corporations can be assigned to groups. These groups are then managed automatically. 
This means that every player who has a character in one of these alliances or corporations will 
automatically become a member of these groups.

Once a group has been removed from all alliances and corporations, it will no longer be managed 
automatically. This also means that all players who are currently members of this group will 
remain so. To correct this, this group can simply be deleted, or it must be assigned a manager 
who can then manually remove all members.

### Group Deactivation

If the ESI token of one or more characters on an account is invalid, the account is disabled. 
A character without a token (no ESI scopes requested during login) counts as invalid.

This means that the API for apps no longer returns groups for that account (if this feature is enabled). 
The deactivation of the account can be delayed, e. g. by 24 hours after a token became invalid.

As soon as the token is updated by logging in with this character, the account will be reactivated.

A mail notification can be sent for deactivated accounts. This mail will only be sent once and 
only if one of the characters in the account is a member of an alliance that was 
previously configured. It will be sent to the main character, if any, or to any of the characters 
that have an invalid token.

### Required Groups

Other groups can be added to a group as a prerequisite. This means that players must be members of one 
of these other groups, otherwise they will automatically be removed from the group.

This check is also done for "managed" Player accounts (see "Account status" above).

## Console Application

The console application has commands to:
- update characters with information from ESI, like corporation etc. 
- check ESI tokens of all character
- perform automatic group assignment based on corporation and alliance to group configuration
- update member tracking data
- send EVE mail to deactivated accounts

## Data Structure (Backend)

 (partial representation)
 
![Entity–relationship model](er-model.png)

- `players` identifies EVE players. Each player account can have one or more `characters`. One
  character is marked as the "Main" character, the rest are "Alts".
- `apps` are 3rd party applications that have access to the "Application API". They can have several groups.
- A player account can be member of several `groups`.
- A player account can be manager of several groups and apps.
- `corporations` and `alliances` can have several groups for automatic group assignments.
- `roles` define what a player or app can do.
