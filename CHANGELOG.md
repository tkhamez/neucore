
## 0.8.0

22 Apr 2019

- New: Membership in one group can now be made dependent on another group membership 
  (see documentation [Required Groups](doc/documentation.md#required-groups).
- New: error limit for applications (only for esi endpoints).
- New: `removed-characters` endpoint for apps.
- BC-Break: DB migrations no longer add data, this is now done with Doctrine data fixtures. If you update 
  from a version lower than 0.7.0, you must manually add these new roles  to your existing applications 
  (if desired): `app-groups`, `app-chars`.
- BC-Break: "Player Groups Admin" is now called "Player Group Management" and requires the new role `user-manager`
  (instead of `user-admin`).
- BC-Break: Group applications revised, all existing applications are *deleted* with the update.
- BC-Break: The console command `make-admin` accepts now the Neucore player ID instead of the EVE character ID.
- Added player ID to account name everywhere.
- Added support for encrypted MySQL connection.
- Layout fixes.

## 0.7.0

13 Mar 2019

- Added "managed" accounts (see documentation [Account status](doc/documentation.md#account-status)).
- Added ESI "proxy" endpoint for apps.
- Added cache for ESI data.
- Added app endpoint that combines the player groups, corp groups and alliance groups endpoints.
- Added application-api.json interface file that contains only the API for applications.
- Implemented more fine grained permissions for apps (new roles app-groups and app-chars).
- Added themes.
- Several UI improvements.
- Added script that creates a build for distribution.
- Other small stuff.

## 0.6.0

31 Dec 2018

- Added corporation member tracking

## 0.5.1

23 Dec 2018

- Waiting time between sending mails increased in order not to trigger the ESI rate limit.
- Dropped Node.js 6.x support
- Updated dependencies

## 0.5.0

8 Dec 2018

New functions:

- Group deactivation for accounts: If one or more characters in a player account have an invalid ESI token, 
  the third-party application API will no longer return groups for that account. This must be enabled in 
  system settings. There is also a configurable delay for it.
- Optional EVE mail notification for disabled accounts.
- Character deletion: If a character has been transferred to another EVE account, it will be deleted or, 
  if detected during login, moved to a new player account. Biomassed characters (Doomheim) are now also 
  deleted automatically.
- Players can now also delete their characters manually, this must be enabled in the system settings.
- System settings: Some things can now be configured or activated/deactivated, needs the new role "settings".

Enhancements/changes:

- Third-party API: Added endpoint to get all characters from a player account.
- Third-party API: added reason phrases for 404 errors (v2 endpoints, no BC break).

Other things:

- minor user interface improvements
- small bug fixes
- some backend refactoring

## 0.4.0

24 Aug 2018

User interface completed.

Fully functional frontend for all API endpoints except for group membership requests.

## 0.3.0

8 Jul 2018

UI for Group Management

## 0.2.0

27 May 2018

- Automatic group assignment for alliances
- API for Apps: Added an endpoint to get groups of corporations and alliances.
- A character's manual update now also updates the player's groups.
- Some minor improvements and fixes

## 0.1.0

6 May 2018

First release.
