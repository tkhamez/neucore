# Brave Collective Core Services

[![Build Status](https://api.travis-ci.org/tkhamez/brvneucore.svg?branch=master)](https://travis-ci.org/tkhamez/brvneucore)
[![Maintainability](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/maintainability)](https://codeclimate.com/github/tkhamez/brvneucore/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/test_coverage)](https://codeclimate.com/github/tkhamez/brvneucore/test_coverage)
[![StyleCI](https://styleci.io/repos/115431007/shield?branch=master)](https://styleci.io/repos/115431007)

https://brvneucore.herokuapp.com

API: https://brvneucore.herokuapp.com/api

## General

Objectives
- Manage alliance specific groups for players.
- Provide an API for authorized third-party applications to query these groups.

This project consists of two applications, the back-end and the front-end.
See the [**front-end**](frontend/README.md) and [**back-end**](backend/README.md) Readme for more.

## Installation

### EVE API setup

- visit https://developers.eveonline.com/applications
- create a new application (eg: brvneucore-dev)
- Connection Type: "Authentication & API Access", add these Scopes:
  - publicData
- set the callback to https://localhost/api/user/auth/callback (change domain/port as required)

### Vagrant Requirements

For synced folder with NFS (instead of rsync), install `nfs-kernel-server` and edit Vagrantfile:
```
- config.vm.synced_folder "./", "/var/www/bravecore"
+ config.vm.synced_folder "./", "/var/www/bravecore", :nfs => true
```

- `vagrant up`
- browse to https://localhost:8443
- If the vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

### Local dev Requirements

- PHP with Composer (see Vagrantfile for necessary additional extensions)
- Node.js + npm
- MySQL/MariaDB
- Apache, set the document root to the "web" directory.

### Install dev

Copy `backend/.env.dist` file to `backend/.env` and adjust values.

Install dependencies and build backend and frontend:
```
./install.sh
```

### Install prod

Set the required environment variables, see in file `backend/.env.dist`.

Make sure that the webserver can write in `backend/var/logs`.

Execute:
```
./install.sh prod
```

### Heroku

To deploy to Heroku, add buildpacks in this order:
```
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

Logs are streamed to `stderr`, not written to files.

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
