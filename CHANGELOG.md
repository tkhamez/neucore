# Changelog

## next (1.5.0)

- **BC break**: Raised minimum required PHP Version to 7.2.0
- **BC break**: Raised minimum required Node.js Version to 10.13.0
- Update to Slim 4
- Update to Babel 7
- Refactored frontend to use a runtime-only build
- User admin now also displays "incoming" characters that have been moved from another account.
- Some UI and performance improvements
- Fix: token state flag for SSOv2 tokens without scopes.

## 1.4.3

21 Sep 2019

- Member Tracking: even more improvements.
- Bug fixes.

## 1.4.2

1 Sep 2019

- API **BC break**: CorporationMember model changed
- Member Tracking: more fixes and improvements.
- Documentation: fixes and improvements.

## 1.4.1

31 Aug 2019

- Fixed/Improved Member Tracking page.

## 1.4.0

21 Aug 2019

- **Breaking**: requires gmp PHP extension
- Switch to SSO v2 [#15][i15]
- Switch to OpenAPI 3.0: there is a new OpenAPI interface description file at `/application-api-3.yml` 
  for the "App" API in OpenAPI version 3.0 format. The file `/application-api.json` in Swagger version 2.0 
  format is still available, but will not be updated anymore. [#9][i9]
- Memory consumption of cron jobs significantly reduced
- Added ESI error limit checking to the "update" commands and delayed execution if it is too low.
- Frontend fix: Filter for member tracking by token status change date does not work. [#25][i25]
- Some preparations for the Slim 4 Update [#24][i24]
- Other small stuff

[i9]: https://github.com/tkhamez/neucore/issues/9
[i25]: https://github.com/tkhamez/neucore/issues/25
[i15]: https://github.com/tkhamez/neucore/issues/15
[i24]: https://github.com/tkhamez/neucore/issues/24

## 1.3.0

4 Aug 2019

- App API: new endpoint that accepts an EVE corporation ID and returns a list of all player IDs that have a 
  character in the corporation.
- App API: new endpoint that accepts a player ID and returns all characters from that account.
- Member tracking: added more filter options for the member list
- Small improvements for UI, frontend and documentation.

## 1.2.1

20 Jul 2019

- Fix: Edge does not load theme stylesheet.
- UI: Optimization for small screens.
- The minimum required Node.js version has been increased to 8.12.0.

## 1.2.0

30 Jun 2019

- Member tracking: added option to limit to members that do not belong to a player account.
- Added command to delete expired Guzzle cache entries.

## 1.1.1

22 Jun 2019

Fix "Core does not detect a character transfer" [#23][i23]

[i23]: https://github.com/tkhamez/neucore/issues/23

## 1.1.0

16 Jun 2019

- New: Optional text area on the home page with customizable text that supports Markdown syntax. [#21][i21]
- Group management: added action buttons directly to the search result [#20][i20]
- User admin: added list of accounts with missing ESI tokens [#16][i16]
- Cron jobs: reduced number of log entries, reduced sleep time.
- Log format is now configurable via optional environment variable BRAVECORE_LOG_FORMAT:
  multiline (default), line (no stacktrace), fluentd, gelf, html, json, loggly, logstash
- Other small stuff/fixes

[i21]: https://github.com/tkhamez/neucore/issues/21
[i20]: https://github.com/tkhamez/neucore/issues/20
[i16]: https://github.com/tkhamez/neucore/issues/16

## 1.0.1

7 Jun 2019

- Configurable location and rotation of log files. [#12][i12]
- Configurable cache directory. [#18][i18]
- DI container no longer caches values of environment variables. [#17][i17]
- Improved loading time of the theme css file. [#11][i11]
- Added environment variable to optionally disable the secure attribute on the session cookie.

[i12]: https://github.com/tkhamez/neucore/issues/12
[i17]: https://github.com/tkhamez/neucore/issues/17
[i11]: https://github.com/tkhamez/neucore/issues/11
[i18]: https://github.com/tkhamez/neucore/issues/18

## 1.0.0

5 May 2019

- New: Customization for some texts, links and images and the default theme.
- New: UI for requestable groups.
- New: user admins can delete any character without creating a "removed character" database entry.

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
