# Documentation

<!-- toc -->

- [Features](#features)
- [Installation and Configuration](#installation-and-configuration)
- [Application API](#application-api)
  * [Authentication](#authentication)
  * [Rate Limit](#rate-limit)
  * [ESI Proxy](#esi-proxy)
- [Service Registration Plugins](#service-registration-plugins)
- [Player Accounts](#player-accounts)
  * [Character Registration](#character-registration)
  * [Removing Characters](#removing-characters)
  * [Account status](#account-status)
- [Multiple EVE Logins](#multiple-eve-logins)
- [Groups](#groups)
  * [Automatic Group Assignment](#automatic-group-assignment)
  * [Group Deactivation](#group-deactivation)
  * [Required and Forbidden Groups](#required-and-forbidden-groups)
  * [Group Applications](#group-applications)
- [Member Tracking](#member-tracking)
- [Watchlist](#watchlist)
- [Mail Notifications](#mail-notifications)
  * [Invalid ESI Token](#invalid-esi-token)
  * [Missing Character](#missing-character)
- [Console Application](#console-application)
- [Data Structure (Backend)](#data-structure-backend)

<!-- tocstop -->

## Features

* EVE SSO login with configurable permission scopes.
* Player accounts with multiple characters.
* Multiple ESI tokens per character with different configurable scopes.
* Customization of texts, links and images specific to your organization, including themes.
* Role based permission system.
* Creation of groups and apps with managers.
* Automatic group assignment for players based on corporations and alliances from all of their characters.
* Advanced group configuration: private, public, default, required and forbidden groups.
* Member applications for groups, optionally automatically acceptable.
* Optional automatic temporary removal of groups if an ESI token is invalid.
* Optional alternative login that does not require any ESI scopes (e.g. for guest account).
* Corporation member tracking.
* Optional EVE mail notifications for invalid ESI tokens and missing characters (via member tracking).
* Configurable watchlists with accounts that have characters in other alliances or corporations.
* An API for applications to query group membership of characters, ESI data and more.
* An [ESI](http://esi.evetech.net) proxy for all characters and their tokens, optionally available for apps, 
  compatible with the ESI OpenAPI definition file. See also [api-examples](api-examples).
* Service registration via [plugins](https://github.com/tkhamez/neucore-plugin).
* Configurable rate limits for apps or IP-based for request to the backend.
* CLI commands for data updates from ESI.
* Usable on small touch screens.

All API functions are documented with OpenAPI and can be found at 
[https://your.domain/api.html](https://neucore.tian-space.net/api). Most of these endpoints are for the frontend, 
only the routes listed in the `Application` groups are for Neucore applications. For these there is also a 
separate OpenAPI definition file at 
[https://your.domain/application-api-3.yml](https://neucore.tian-space.net/application-api-3.yml).

## Installation and Configuration

See [Install.md](Install.md).

Read `backend/.env.dist` for some optional configuration that is not (yet) described here.

## Application API

### Authentication

First an application must be created by an app administrator and assigned to an app manager, 
who can then generate the app secret.

Apps are authenticated using an HTTP authorization header.

The authorization string is composed of the word Bearer followed by a base64-encoded
string containing the app ID and secret separated by a colon (1:my awesome secret).

Example:
```
curl --header "Authorization: Bearer MTpteSBhd2Vzb21lIHNlY3JldA==" https://neucore.tld/api/app/v1/show
```

### Rate Limit

If the API rate limit is enabled (UI: Admin -> Settings -> Features), each response will contain 
the headers `X-Neucore-Rate-Limit-Remain` and `X-Neucore-Rate-Limit-Reset`. A request results in an error 
429 "Too many requests" if the limit has been exceeded.

If the IP-based rate limit is also active, the headers contain the values of the rate limit 
with the lower "remain" value.

If it is configured only, but not active, it is logged when an app exceeds the limit.

### ESI Proxy 

The endpoint `/app/v2/esi` acts as an ESI proxy for each character token that was added.

There's a command `bin/console check-tokens` that can be used as a cronjob to keep the refresh token valid. It 
also implements refresh token rotation.

The API respects the ESI cache headers and will return a cached response if it is still valid.

#### ESI Error Limit

The ESI error limit of 100 errors every 60 seconds is reduced to 80 errors every 60 seconds. It is  
shared between all applications that use the ESI API endpoint. The `X-Esi-Error-Limit-Remain` header is
*not* modified to reflect this.

## Service Registration Plugins

- Create a new PHP application with composer and install tkhamez/neucore-plugin:
  ```shell script
  composer init
  composer require tkhamez/neucore-plugin
  ```
- Create a new class and implement `Neucore\Plugin\ServiceInterface`.
- In Neucore, go to Administration -> Services and add a new service.
- Configure the service, at the very least set "PHP Class", "PSR-4 Prefix" and "PSR-4 Path".

## Player Accounts

### Character Registration

Each EVE character belongs to a player account, an account can have several characters.

When a character logs in via EVE SSO for the first time, a new player account is created
and that character is marked as the main character.

After a successful login, additional characters (alts) can be added to the account. This
is also done via EVE SSO.

If a character to be added to an account already belongs to another account, both accounts will
be merged by moving all characters from the newer account to the older. This can happen, for example,
if someone has accidentally created two accounts by logging in with an alt that has not
yet been added to the main account.

### Removing Characters

When it was detected that an EVE character was deleted or was transferred to another EVE account, 
it will be removed from its current player account.

An admin can also manually delete a character, a player can do this if that is enabled in the system setting.

All character removals are recorded and are visible to user admins.

### Account status

There a two account status: standard and managed.

- The status can be changed at any time by a user admin.
- If the status is changed, all groups are removed. New groups can be added manually in the same way as for normal 
  accounts.
- Automatic group assignment is disabled for managed accounts, "Required Groups" are still checked, see below.
- Groups are never deactivated for managed accounts.
- There is a separate login URL for managed accounts that does not require ESI scopes 
  (must be allowed in the settings).

## Multiple EVE Logins

Any number of EVE logins with different ESI scopes can be configured. Players can add ESI tokens to their 
characters for each of these logins.

EVE logins can be added to apps so that they can use their tokens. Apps can always use the token of 
the default login (core.default).

## Groups

Visibility
- public: everyone can see them and apply to them.
- private: hidden from non-members

A group can be configured to be added to every account. This still obeys the configurable requirements to
be in that group.

### Automatic Group Assignment

Alliances and corporations can be assigned to groups. These groups are then managed automatically. 
This means that every player who has a character in one of these alliances or corporations will 
automatically become a member of these groups.

Once a group has been removed from all alliances and corporations, it will no longer be managed 
automatically. This also means that all players who are currently members of this group will 
remain so. To correct this, this group can simply be deleted, or it must be assigned a manager 
who can then manually remove all members.

### Group Deactivation

If the ESI token of one or more characters on an account is invalid, the account can be disabled. This is done 
on the settings page, feature "Deactivate Groups". A character without a token (no ESI scopes were requested 
during login) counts as invalid.

Deactivation means that the API for apps no longer returns groups for that account. The deactivation of the 
account can be delayed, e. g. by 24 hours after a token became invalid.

As soon as the token was updated by logging in with the appropriate character, the account will be reactivated.

### Required and Forbidden Groups

Other groups can be added to a group as a prerequisite. This means that players must be members of at least one of 
these other groups, otherwise they will be automatically removed from the group.

It is also possible to configure groups that an account cannot be a member of to be a member of that group.

Those check is also done for "managed" Player accounts (see "Account status" above).

### Group Applications

Groups can be configured to be available for members to apply to. Managers can then accept or deny those applications,
it's also possible to automatically accept applications.

## Member Tracking

Access to corporation member tracking data is configured by adding groups to a corporation whose 
members are allowed to see the data of that corporation.

The "tracking" role is automatically added to or removed from the player when this configuration is changed or 
members are added or removed from these groups.

## Watchlist

Corporations can be automatically added to the allowlist (and removed accordingly) if all their members 
are on the same account using the `auto-allowlist` command. This only works if at least one character in
that corporation has authorized the `esi-corporations.read_corporation_membership.v1` ESI scope.

The permissions are managed via groups, one for viewing and one for administration separately for each watch list.

## Mail Notifications

### Invalid ESI Token

An EVE mail can be sent for accounts with characters with an invalid ESI token.

This mail will only be sent once and only if one of the characters in the account is a member of an alliance 
that was previously configured. It will be sent to the main character, if any, or to one of the characters that 
have an invalid token.

### Missing Character

An EVE mail can be sent to characters that were not added to an account.

This mail will only be sent to members of configured corporations where member tracking must be enabled. It will 
only be sent if the character has logged in within a configurable number of days and will be sent again after the 
same number of days.

## Console Application

The console application has commands to:
- update characters with information from ESI, like corporation etc.
- check ESI tokens of all characters.
- perform automatic group assignments.
- update member tracking data.
- update auto allowlist of watchlists.
- update service accounts.
- send EVE mail to accounts with deactivated groups.
- delete expired HTTP cache entries.

See `backend/bin/run-jobs.sh` for a script that runs them all in a sensible order.

## Data Structure (Backend)

 (partial representation)
 
![Entity–relationship model](er-model.png)

- `players` identifies EVE players. Each player account can have one or more `characters`. One
  character is marked as the "Main" character, the rest are "Alts".
- `apps` are applications that have access to the "Application API". They can have several groups.
- A player account can be member of several `groups`.
- A player account can be manager of several groups and apps.
- `corporations` and `alliances` can have several groups for automatic group assignments.
- `roles` define what a player or app can do.
