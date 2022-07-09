[![build](https://github.com/tkhamez/neucore/workflows/build/badge.svg)](https://github.com/tkhamez/neucore/actions)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=brvneucore&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=brvneucore)
[![Open Hub](https://www.openhub.net/p/neucore/widgets/project_thin_badge?format=gif)](https://www.openhub.net/p/neucore/)
[![Discord](https://badgen.net/badge/icon/discord?icon=discord&label)](https://discord.gg/memUh56u8z)

# Neucore - Alliance Core Services

An application for managing access for [EVE Online](https://www.eveonline.com/) players to external services 
of an alliance.

## Table of Contents

<!-- toc -->

- [Overview](#overview)
- [Getting started](#getting-started)
  * [Installation](#installation)
  * [First login and Customization](#first-login-and-customization)
  * [Setting up Member Tracking and Watchlist](#setting-up-member-tracking-and-watchlist)
- [Final Notes](#final-notes)
  * [Origin](#origin)
  * [Related Software](#related-software)
  * [Contact](#contact)
  * [Copyright notice](#copyright-notice)

<!-- tocstop -->

## Overview

Main features:

- Login via EVE SSO, no e-mail address required.
- Group membership management (automatic and manual).
- Service registration via [plugins](https://github.com/tkhamez/neucore-plugin).
- [ESI](http://esi.evetech.net) proxy for all characters.
- Corporation member tracking and watchlists.
- An API for applications to read group membership, ESI data, and more.

For more information, see the `doc` directory, including [**Documentation**](doc/Documentation.md), 
an [**API**](doc/API.md) overview, and some [screenshots](doc/screenshots).

A preview/demo installation is available at https://neucore.herokuapp.com.

## Getting started

### Installation

See [doc/Install.md](doc/Install.md) for installation instructions.

### First login and Customization

Read the backend documentation on how to [make yourself an admin](backend/README.md#making-yourself-an-admin),
then you can navigate to "Admin" -> "Settings" and change texts, links and images that are specific to your 
installation.

### Setting up Member Tracking and Watchlists

Group for permissions
- Go to Administration -> Groups, add a new group and add yourself as a manager.
- Go to Management -> Groups, select the new group and add yourself as a member.

Member Tracking
- Go to Administration -> Settings -> Directors and use the login link to add a character with director roles 
  for your corporation.
- Open a console and run `backend/bin/console update-member-tracking`.
- Go to Administration -> Tracking, select your corporation and add a group you are a member of.
- Go to Member Data -> Member Tracking and select your corporation.

Watchlist
- Go to Administration -> Watchlist and add a new watchlist. Open the "View" and "Manage" tabs and add your group.
- Go to Member Data -> Watchlist -> Settings and add alliances and/or corporations for watching.

## Final Notes

### Origin

The software was originally developed for the [Brave Collective](https://www.bravecollective.com), 
when CCP shut down the old API and we had to replace our Core system.

This is also where the name "Neucore" (new Core) comes from.

### Related Software

- Plugin package: [neucore-plugin](https://github.com/tkhamez/neucore-plugin).
- Discord auth plugin: [neucore-discord-plugin](https://github.com/tkhamez/neucore-discord-plugin).
- Plugins from Brave Collective for [Slack](https://github.com/bravecollective/neucore-plugin-slack),
  [Mumble](https://github.com/bravecollective/neucore-plugin-mumble) and 
  [phpBB forum](https://github.com/bravecollective/neucore-plugin-forum).
- OpenAPI clients: [PHP](https://github.com/tkhamez/neucore-api), 
  [Python](https://github.com/tkhamez/neucore-api-python), [Go](https://github.com/tkhamez/neucore-api-go).
- [slack-channel-manage](https://github.com/tkhamez/slack-channel-manage) A Slack app to 
  manage channel members based on Neucore groups.
- [Eve Overseer](https://github.com/1adog1/eve-overseer) A fleet participation tracking application.
- [Eve Pingboard](https://github.com/cmd-johnson/eve-pingboard) Pings/Timers/Calendar.
- [Neucore connector boilerplate](https://github.com/bravecollective/neucore-connector-boilerplate) 
  An example application that uses EVE SSO and Neucore groups for access control.
- A [TimerBoard](https://github.com/tkhamez/neucore-timerboard) (based on the boilerplate).
- A [Ping](https://github.com/bravecollective/ping-app) app for Slack.
- [EVE-SRP](https://github.com/bravecollective/evesrp/tree/feature/braveneucore) integration.

### Contact

If you have any questions or feedback, you can join the [Neucore Discord Server](https://discord.gg/memUh56u8z) or
contact [Tian Khamez](https://evewho.com/character/96061222) on 
[Tweetfleet Slack](https://tweetfleet.slack.com) (get invites 
[here](https://www.fuzzwork.co.uk/tweetfleet-slack-invites/)).

### Copyright notice

Neucore is licensed under the [MIT license](LICENSE).

"EVE", "EVE Online", "CCP" and all related logos and images are trademarks or registered trademarks of
[CCP hf](http://www.ccpgames.com/).
