[![Build Status](https://travis-ci.com/tkhamez/neucore.svg?branch=master)](https://travis-ci.com/tkhamez/neucore)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=brvneucore&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=brvneucore)

# Neucore

An application for managing access for [EVE Online](https://www.eveonline.com/) players to external services 
of an alliance.

## Table of Contents

<!-- toc -->

- [Overview](#overview)
- [Getting started](#getting-started)
  * [First login and Customization](#first-login-and-customization)
  * [Setting up Member Tracking and Watchlist](#setting-up-member-tracking-and-watchlist)
- [Final Notes](#final-notes)
  * [Origin](#origin)
  * [Related Software](#related-software)
  * [Contact](#contact)
  * [Copyright notice](#copyright-notice)

<!-- tocstop -->

## Overview

Objectives:

- Management of groups for players.
- An API for applications to read these groups (and more).
- Access to [ESI](http://esi.evetech.net) data of all members.
- Member tracking and watch lists.
- Login via EVE SSO.

For more information, see the `doc` directory, including [**Documentation**](doc/Documentation.md), 
an [**API**](doc/API.md) overview, and some [screenshots](doc/screenshots).

This project consists of two applications, the [**Backend**](backend/README.md) 
and the [**Frontend**](frontend/README.md).

A preview/demo installation is available at https://neucore.herokuapp.com.

## Getting started

See [doc/Install.md](doc/Install.md) for installation instructions.

### First login and Customization

Read the backend documentation on how to [make yourself an admin](backend/README.md#making-yourself-an-admin),
then you can navigate to "Admin" -> "Settings" and change texts, links and images that are specific to your 
installation.

### Setting up Member Tracking and Watchlist

Group for permissions
- Go to Administration -> Groups, add a new group and add yourself as a manager. (If you want to make it a 
  requestable group, edit it and make it public.)
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

This is also where the name "Neucore" comes from.

### Related Software

- [neucore-api](https://github.com/bravecollective/neucore-api) PHP OpenAPI client
- [neucore-api-python](https://github.com/bravecollective/neucore-api-python) Python OpenAPI client
- [Neucore connector boilerplate](https://github.com/bravecollective/neucore-connector-boilerplate) 
  An example application that uses EVE SSO and Neucore groups for access control.
- A [TimerBoard](https://github.com/tkhamez/neucore-timerboard) (based on the boilerplate).
- Neucore integration with other apps:
  [EVE-SRP](https://github.com/eve-n0rman/evesrp/tree/feature/braveneucore),
  [phpBB](https://github.com/bravecollective/forum-auth),
  [Mumble](https://github.com/bravecollective/mumble-sso),
  [Slack](https://github.com/bravecollective/slack-signup).

### Contact

If you have any questions or feedback, you can contact Tian Khamez on [Tweetfleet Slack](https://tweetfleet.slack.com)
(get invites [here](https://www.fuzzwork.co.uk/tweetfleet-slack-invites/)).

### Copyright notice

Neucore is licensed under the [MIT license](LICENSE).

"EVE", "EVE Online", "CCP" and all related logos and images are trademarks or registered trademarks of
[CCP hf](http://www.ccpgames.com/).
