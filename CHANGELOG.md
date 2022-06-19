# Changelog

## next

dd mm yyyy

- **BC Break** Updated [neucore-plugin](https://github.com/tkhamez/neucore-plugin) to 0.8.0, all plugins
  must be updated, or they will stop working.
- **BC Break**: The `/core.alt` and `/core.managed-alt` login URLs have been removed. Use `core.default` and 
  `core.managed` respectively.
- Change: It is no longer possible to freely move characters between accounts. Instead, accounts are now merged when 
  someone logs in with a character from another account while already logged in.
- Added new reason for character deletion for admins: lost access.
- The character's corporation is now also set to Doomheim if the character update returns a 404
  "Character has been deleted" response. This can be faster than an update via affiliations.
- Small UI improvements.

ESI endpoint for apps:
- Added `/app/v2/esi` and deprecated `/app/v1/esi`. The new version contains error messages in the body
  instead of the reason phrase.
- Added checks for ESI 429 errors (rate limit and throttled). If these have occurred and the wait time has not 
  yet expired, Core now returns a 429 error itself instead of making the ESI request.
- Added `Retry-After` header for 429 errors from `/app/v2/esi` that are from Core, not ESI.
- The `Retry-After` header from ESI is now also passed through, if it is present.
- Fixed type of `Expires` header from `integer` to `string` in OpenAPI definition file for `/app/v1/esi`.

## 1.32.0

3 Jun 2022

- New: The user agent for the HTTP client is now configurable (optional).
- New: Configuration to reduce ESI 403 errors from structure name updates (Administration -> Settings -> 
  Features -> Structure Name Updates) (`update-member-tracking` command).
- New: The time of the last check was added to the ESI tokens.
- New: Added "characters" option to the `check-token` command to optionally reduce the number of ESI tokens that
  are validated.
- Change: Structure names are no longer updated more than once during one run (`update-member-tracking` command).
- Change: ESI tokens that are marked as invalid are no longer validated.
- Change: Adjusted default sleep times for some cron jobs.
- Fix: Re-added missing director token for structure name updates (member tracking).
- Text improvements.
- Updated dependencies.
- Dev: Removed Node.js 14 support.
- Dev: (Re)moved .drone.yml.

## 1.31.1

5 Feb 2022

- Fix Member Tracking: Background was not always removed when the character modal window was closed.
- Some small text and layout improvements.

## 1.31.0

16 Jan 2022

- **BC Break** Updated [tkhamez/neucore-plugin](https://github.com/tkhamez/neucore-plugin) to 0.7.0.
- New: Added update button to update all service accounts from a player to the characters modal and user admin page.
- New: Added redirect after login to the previous URL.
- Small bug fixes.

## 1.30.0

9 Jan 2022

- New: Groups can now be marked as default, which adds them to any account. The configuration of the required 
  groups is still respected. Members will not be removed if a group is no longer marked as default.
- New: "Forbidden" groups have been added in the group configuration. An account is removed from a group if it is a 
  member of one of its forbidden groups.

## 1.29.0

1 Jan 2022

- **BC Break** Updated tkhamez/neucore-plugin to 0.6.0: One account property changed its name, from 
  `displayName` to `name`. This needs to be adjusted on the Service configuration page if it is used.
- PHP 8.1 compatibility.
- Added optional environment variable NEUCORE_ERROR_REPORTING to change the error reporting level.
- Update to Bootstrap 5 (from 4).
- UI fixes and improvements.

## 1.28.0

12 Dec 2021

- **BC break**: Increased min. PHP version to 7.4.
- Increased min. Node.js version to 14.15.
- Fixed: App API ESI response did not contain error message in body.
- Updated dependencies.

## 1.27.1

15 Nov 2021

- Adjustment of detection of deleted characters after ESI change.
- Do not try to get roles from ESI of characters without ESI token.

## 1.27.0

23 Oct 2021

- Always update the refresh token in the database after successfully refreshed the access token.
- Always delete invalid refresh token from the database after unsuccessfully requesting a new access token.
- Implemented wait time (Retry-After header) after a 429 response from SSO.
- Fixed display of error message from service plugins.
- Fixed HTTP User-Agent header for requests with the OpenAPI client.
- Fixed revoke token request.
- Updated dependencies.

## 1.26.0

18 Sep 2021

- **BC break**: Removed `run-jobs2.sh`.
- Updated dependencies.

## 1.25.0

17 Sep 2021

- **BC break**: Updated tkhamez/neucore-plugin to 0.5.0, all plugins must be updated, or they will stop working.
- **BC break**: The "Required Groups" configuration was changed from AND to OR, so only one is now required
  instead of all of them.
- Implemented everything that is necessary for the [Discord plugin](https://github.com/tkhamez/neucore-discord-plugin)
- Fix: EVE Logins dropdown was empty on App Administration page

## 1.24.0

4 Sep 2021

- **Note** This update includes a big database update, make sure to create a backup before updating.
- **BC break**: Updated tkhamez/neucore-plugin to 0.4.0.  
  Because of the change in 0.3.0 all plugins must be updated, or they will stop working. It adds 
  "Configuration Data" to the service plugin configuration. This data is available in the class
  implementing Neucore\Plugin\ServiceInterface.
- Added "Display Name" to service plugins (with plugin version 0.4.0).
- New: Multiple ESI tokens per character with configurable scopes. [#32][i32]
- Added token status change date to character modal.
- Added reason "moved-owner-changed" (used instead of "moved" if applicable) to "RemovedCharacter".
- Renamed "Find Alts" page to "Characters".
- Removed "invalid token" and "no token" account lists from user admin page.
- Removed "managed-alt" login link on home page for managed accounts.
- Small text improvements for the service admin page and better error handling when the service configuration 
  is invalid.
- Other small UI improvements.
- Updated dependencies.

[i32]: https://github.com/tkhamez/neucore/issues/32

## 1.23.1

21 May 2021

- Fix some required group checks.

## 1.23.0

21 May 2021

- **BC break**: If a group has other groups as prerequisites, the player now only needs to be a member of 
  one of those groups instead of all of them.
- New: Display public/private and auto-accept flag for group managers.
- New: Display the number of members of groups.
- New: Show the player's corporation in the group members list.
- New: Show character modal button for user managers if they have the "user-chars" role.
- Change: Previously, the account in the member tracking list was updated only when the other data was updated 
  as well. This is no longer the case and instead the account display is now always up-to-date.
- Change: When a player is removed from a group, their application is now also removed, if there is one.
- Fix: Delete group description from input field when group was deleted.
- Update dependencies.

## 1.22.0

14 May 2021

- Groups can now be set to "automatically accept" so that applications do not have to be approved.
- Added description to groups.
- Some improvements and bug fixes for the group application process.
- Exclude renamed and moved characters from some character searches.
- Added corporation filter to the group members endpoint (app API).
- Added CSRF Token to the API description.

## 1.21.0

9 May 2021

- New API endpoints for apps: 
  - GET /app/v1/group-members/{groupId} Returns the main character IDs from all group members.
  - POST /app/v1/characters Returns all characters from multiple player accounts identified by character IDs.
- Changed check for supported browsers.
- Removed duplicate character search results (from character movements).
- Small bug fixes.
- Updated dependencies.

## 1.20.0

24 Apr 2021

- **BC break**: The file `web/theme.js` has been moved to `web/dist/theme.js` (source location: 
  `frontend/public/theme.js`).
- **BC break**: Drop support for IE 11.
- **BC break** (dev only): Drop support for Node.js 10.
- Frontend: vue-cli 4 added, which changes the build process and downgrades Webpack to v4, from v5.
- Frontend: Update vue.js to version 3.
- Backend: Fixed CORS middleware, added "SameSite" configuration option to Session middleware.
- New: Log a missing character name change if the access token still contains the old name. See also 
  https://github.com/ccpgames/sso-issues/issues/68
- Fix: Group Management accept/deny buttons.

## 1.19.1

2 Apr 2021

- Fix: App request charts were broken.
- Fix: The new function for character renames was not used for cron job.
- Change: The name of a character is no longer updated with the name from the OAuth data after a login, 
  because after a character name change, this name can be the old name.

## 1.19.0

29 Mar 2021

- New: Log character name changes.
- Character search now includes removed and renamed characters and can find characters by ID.
- Focus input field for character search after clicking the "Clear input" button.
- Minor dependency updates.

## 1.18.0

15 Feb 2021

- Character modal: Added groups, moved characters and service accounts.
- Group Management: Added list of managers.
- User Admin: Overview of service accounts added.
- Tracking Admin: Added the ticker to corporations in the list.
- Player Groups Management: The left column is sticky now.
- Watchlist: Sort dropdown by name.
- Settings - Directors: Sort corporations by name.
- Home: Renamed "Update ESI data" button to "Update corporation".
- Added "player" option to "update-player-groups" command.
- The "update player groups" function now also synchronizes the manager role, just in case there was an error before.
- Improved performance of permission check for "/api/user/player/{id}/characters".

## 1.17.1

31 Jan 2021

- Reduced memory usage of "update-service-accounts" job.
- Added path checking for CSRF token middleware so that it does not run for the "app" API.
- Updated dependencies.

## 1.17.0

30 Jan 2021

- **BC break**: Service registration plugins must implemented [v0.2.0](https://github.com/tkhamez/neucore-plugin) 
  now, updated from 0.1.0.
- Services: Added "update-service-accounts" command and added it to `bin/run-jobs.sh`.
- Services: Service accounts are now reloaded after a manual update (frontend).
- New: Charts with statistics on player logins and app requests.
- New: Added CSRF protection.
- New: Added option to enable Strict-Transport-Security.
- ESI: Added check for "Undefined 429 response", see also https://github.com/esi/esi-issues/issues/1227
- ESI: Do not query different types at once via "universe/names".
- Tracking: Trim search.
- Tools: Added shell script to build the distribution with docker.

## 1.16.2

16 Jan 2021

- Services: It is now checked whether groups are deactivated.
- Fix "There is no active transaction" error during DB migrations with PHP 8.

## 1.16.1

10 Jan 2021

- Add configurable text (markdown) on the login page below the login button.

## 1.16.0

9 Jan 2021

- Experimental: Service registration plugins.
- Navigation: Do not show "Groups" link if there are no public groups.
- Rate limit: Always log if the configured limit was reached, even if it's not active.
- Set SameSite attribute for session cookie.
- Other small improvements.
- Compatibility with PHP 8.0

## 1.15.2

23 Nov 2020

- Add compression for frontend production build.

## 1.15.1

23 Nov 2020

- Added Content-Length header for JSON responses to enable compression with nginx.
- Fixed frontend build for IE 11.
- Fixed member tracking table sort for columns with HTML.

## 1.15.0

23 Nov 2020

- **BC break**: Raised minimum required PHP version to 7.3.0
- **BC break** (dev only): Raised minimum Node.js version to 10.16.0 (with npm 6.9.0)
- **BC break** The default theme has been removed from the settings. Instead, there is now a configuration file 
  `web/theme.js`.
- Added statistics with number of logins per account and month.
- Added statistics with number of requests per app and day.
- Changed sleep time for update-player-groups command.
- Removed the debug log of application rate limits.
- Several small fixes.
- Replaced Google Fonts Plugin with Fontsource.
- Compatibility with Node.js 14.
- Many dependencies updated.

## 1.14.0

6 Aug 2020

- **BC break** The application API is now split into several tags. This can lead to backwards incompatible API clients
  that were generated automatically.
- New: Application API endpoint that returns all known characters from a list of character IDs.
- New: Application API endpoint that returns all known characters from one corporation. 
- New: Added the date when a character was added to the database to the list of characters (UI).
- New: Required groups are now checked when adding new members to a group.
- New: Implemented API and UI to add, rename and delete watchlists.
- New: Lock for the watchlist settings: If enabled, only watchlist admins can add and remove alliances 
  and corporations from the list of watched alliances and corporations. Watchlist managers can still edit the
  kick- and allowlist.
- Change: The watchlist role no longer gives access to all characters from all accounts, but only to accounts that 
  are on a watchlist for which the user has view permission (similar to how it works for the tracking role and member 
  tracking data).
- Change: Inclusive and more descriptive terminology (changed "red flags" to warnings, blacklist to kicklist or 
  denylist and whitelist to allowlist)
- **BC break** The console command `auto-whitelist` was renamed to `auto-allowlist`
- Added `run-jobs2.sh` with `update-chars` and `update-player-groups` jobs.
- Small UI improvements and fixes.

## 1.13.1

13 Jun 2020

- Improved "Find Alts" page layout.
- Fixed Watchlist: Allowlist did not always load. [#30][i30]
- Fixed an error that broke the app in IE 11.
- Other small stuff.

[i30]: https://github.com/tkhamez/neucore/issues/30

## 1.13.0

5 Jun 2020

- New: Added the "Find Alts" page, which accepts a list of character names and returns them grouped by player account. 
  It requires the new role "user-chars".
- Change: **BC break** The character search and the list of alts has been moved from the watchlist to the new page 
  "Find Alts" and now requires the new role "user-chars".
- Change: **BC break** The editing permission for a watchlist is now configured separately for each watchlist 
  using a group, so the role "watchlist-manager" is now also managed automatically.
- Change: The character search for group managers can now only find main characters, no more alts.
- Change: the app-manager and group-manager roles are now added and removed automatically, 
  depending on whether the player is a manager of an app or a group.
- Change: The "auto-allowlist" command no longer needs a watchlist ID, it now runs for all watchlists without it.
- Improvement: Added name of the director with ESI token to the Member Tracking Administration page.
- Fix: The Member Tracking corporation selection now only offers corporation that the user can see.
- Fix: ESI result body was not always displayed correctly.
- Fix: Could not delete character from a corporation with member tracking data.

## 1.12.1

27 May 2020

- Fixed character modal on Watchlist page again.
- Improved some table layouts
- Fixed: watchlist name was changed in data fixture.
- Updated dependencies

## 1.12.0

24 May 2020

- Change: When the main character is removed from an account, another character is now automatically made the main.
- Added `/app/v1/player-with-characters/{characterId}` app API endpoint which returns the player account to which 
  the character ID belongs with all characters.
- Watchlist: Added a watchlist select box and removed hard coded selection. (API and UI for adding, removing and 
  renaming watchlists are still missing.)
- Added rate limiting middleware for the application API.
- APCu storage added for variables that do not need to be stored permanently.
- Small bug fixes and improvements.

## 1.11.6

15 May 2020

- Fix menu.

## 1.11.5

4 May 2020

- Member Tracking: Added "mail count" filter.

## 1.11.4

4 May 2020

- Member Tracking: Added number of sent mails.

## 1.11.3

3 May 2020

- Member Tracking: Added send result from "missing character" mail.
- Member Tracking: Added all form settings to URL.

## 1.11.2

3 May 2020

- Member Tracking: removed "changed" column, added tooltips with ESI token change date and 
  "missing character" mail sending date.

## 1.11.1

2 May 2020

- Reduced CPU usage of tracking role sync.
- Added `Warning` header to the allowlist of the app ESI endpoint.
- Changed environment variable prefix from `BRAVECORE_` to `NEUCORE_` (with fallback, old names still work).

## 1.11.0

1 May 2020

- Increased sleep time of the `update-player-groups` command to reduce CPU usage.
- Added POST requests to the ESI endpoint (GUI, not apps).
- Implemented a debug option for ESI queries (GUI, not apps).
- Moved the PHP error log to `BRAVECORE_LOG_PATH`.

## 1.10.1

26 Apr 2020

- Fixed a bug where a character was not correctly moved to a new account.

## 1.10.0

18 Apr 2020

- **BC break** The `update-chars` command no longer updates corporations and alliances, instead there is a new command
  `update-corporations`.
- New: Added "Update from ESI" button to the character modal.
- New: Added members tab to groups for group admins.
- Change: Added role watchlist-manager with permissions to edit the configuration, 
  edit access is now restricted to watchlist-admin.
- Change: Moved "set account status" function from the role user-admin to user-manager.
- Improvement: The "automatic group assignment" is now performed directly when a character is added or 
  removed from an account.
- Improvement: Unit tests can now also be run with a SQLite in-memory database.
- Fix: Added missing permission to role "watchlist" to use the character search.
- Small UI improvements
- Some refactoring

## 1.9.2

8 Mar 2020

- Fixed character modal on Watchlist page

## 1.9.1

4 Mar 2020

- Updated ESI client
- Fixed alliance/corporation search

## 1.9.0

29 Feb 2020

- New: Automatic inclusion of corporations in the allowlist for the watchlist.
- New: Added character search with list of all characters on a player account to the watchlist page.
- Change: Group managers can no longer see a list of all characters on a player account.
- Change: Access to the list of all characters on a player account for the "tracking" role is now limited to 
  accounts that have a character in a corresponding corporation.
- Change: Trim character search input.
- Integrated Swagger UI build/installation with frontend.
- Other small stuff.

## 1.8.0

26 Dec 2019

- New: Watchlist: Kicklist added.
- New: Watchlist: Allowlist for corporations and alliances added.
- New: Notification mail for characters from tracked corporations that have not yet been added to Neucore.
- New: `incoming-characters` endpoint for apps.
- Added last update date to corporation tracking data.
- Removed `scopes` column from the character table because that information is now (SSOv2) included in the JSON
  Web Token.
- Switched to the new EVE image server.
- Fix: sometimes newly added characters were not updated, so their corporation was missing.
- Other small stuff.

## 1.7.1

24 Nov 2019

- Using `/characters/affiliation/` and `/universe/names/` instead of `/characters/{character_id}/` 
  to update characters (much shorter cache time and faster)

## 1.7.0

21 Nov 2019

- **BC break** Deactivate Groups: This is now limited to accounts with characters in configurable alliances or 
  corporations.
- New: Watchlist (show accounts with characters in other alliances or corporations).
- Invalid ESI token mail: This can now also be sent to members of corporations that are not part of an alliance.
- UI fixes.
- Also update ESI refresh tokens when getting a new access token.
- Other small stuff.

## 1.6.0

12 Nov 2019

- **BC break**: The notification mail "Account disabled" is now called "Invalid ESI token" and is sent 
  regardless of the feature setting "Disable accounts".  
  This mail is deactivated with the update.  
  The command "send-account-disabled-mail" was renamed to "send-invalid-token-mail".
- Member Tracking: Search can now be limited to individual columns.
- API: Added "created" date to the character model.
- UI improvements.
- Google fonts are now bundled (no more requests to fonts.googleapis.com).

## 1.5.1

21 Oct 2019

- Fix: Endpoints POST `/api/app/v1/groups`, POST `/api/app/v1/corp-groups` and POST `/api/app/v1/alliance-groups`.

## 1.5.0

20 Oct 2019

- **BC break**: Raised minimum required PHP Version to 7.2.0
- **BC break**: Raised minimum required Node.js Version to 10.13.0
- **BC break**: URLs like `domain.tdl//api` (note the double slash) do not work anymore.
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

- Fix "Core does not detect a character transfer" [#23][i23]

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
  (see documentation [Required Groups](doc/Documentation.md#required-groups).
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

- Added "managed" accounts (see documentation [Account status](doc/Documentation.md#account-status)).
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

- User interface completed.
- Fully functional frontend for all API endpoints except for group membership requests.

## 0.3.0

8 Jul 2018

- UI for Group Management

## 0.2.0

27 May 2018

- Automatic group assignment for alliances
- API for Apps: Added an endpoint to get groups of corporations and alliances.
- A character's manual update now also updates the player's groups.
- Some minor improvements and fixes

## 0.1.0

6 May 2018

- First release.
