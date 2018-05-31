# Brave Collective Core Services

[![Build Status](https://api.travis-ci.org/tkhamez/brvneucore.svg?branch=master)](https://travis-ci.org/tkhamez/brvneucore)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tkhamez/brvneucore/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tkhamez/brvneucore/?branch=master)
[![Maintainability](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/maintainability)](https://codeclimate.com/github/tkhamez/brvneucore/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/test_coverage)](https://codeclimate.com/github/tkhamez/brvneucore/test_coverage)
[![StyleCI](https://styleci.io/repos/115431007/shield?branch=master)](https://styleci.io/repos/115431007)

Preview: https://brvneucore.herokuapp.com

API: https://brvneucore.herokuapp.com/api

## General

Objectives
- Manage alliance specific groups for players.
- Provide an API for authorized third-party applications to query these groups.

This project consists of two applications, the back-end and the front-end.
See the [**front-end**](frontend/README.md) and [**back-end**](backend/README.md) Readme for more.

There is also a minimal (temporary) front-end in the [**web**](web) directory.

See [**doc/features.md**](doc/features.md) for more.

## Installation

### EVE API setup

- visit https://developers.eveonline.com/applications
- create a new application (eg: brvneucore-dev)
- Connection Type: "Authentication & API Access", add the required scopes. Scopes for the Core back-end
are configured with the environment variable BRAVECORE_EVE_SCOPES.
- set the callback to https://localhost/api/user/auth/callback (change domain/port as required)

### Vagrant

Only tested with Vagrant 2 + libvirt.

- `vagrant up` creates and configures the virtual machine.
- Use `vagrant ssh` and `ifconfig` to determine the IP address.
- Browse to https://localhost:8443
- If the Vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

For synced folder with NFS (instead of rsync), install `nfs-kernel-server` and edit Vagrantfile:
```
- config.vm.synced_folder "./", "/var/www/bravecore"
+ config.vm.synced_folder "./", "/var/www/bravecore", :nfs => true
```

### Local dev Requirements

- PHP 7 with Composer (see Vagrantfile for necessary additional extensions)
- Node.js 8 + npm 5
- MariaDB or MySQL Server
- Apache or another HTTP Server, set the document root to the `web` directory.
- Java (for swagger-codegen)

### App setup

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Make sure that the web server can write in `backend/var/logs`.

In `dev` mode both the web server and SSH user write the same files to `backend/var/cache`,
so make sure they can override each other's files, e. g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories).

Then install the dependencies and build the back-end and front-end by executing:
`./install.sh` or `./install.sh prod`.

### Heroku

To deploy to Heroku, add buildpacks in this order:
```
heroku buildpacks:add heroku/java
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

Logs are streamed to `stderr`, not written to files.
