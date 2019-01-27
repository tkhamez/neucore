# Brave Collective Core Services

[![Build Status](https://api.travis-ci.org/tkhamez/brvneucore.svg?branch=master)](https://travis-ci.org/tkhamez/brvneucore)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tkhamez/brvneucore/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tkhamez/brvneucore/?branch=master)
[![Maintainability](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/maintainability)](https://codeclimate.com/github/tkhamez/brvneucore/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/90884db4cd12869fdcfe/test_coverage)](https://codeclimate.com/github/tkhamez/brvneucore/test_coverage)
[![StyleCI](https://styleci.io/repos/115431007/shield?branch=master)](https://styleci.io/repos/115431007)

Preview https://brvneucore.herokuapp.com
([Swagger UI](https://brvneucore.herokuapp.com/api), 
[OpenAPI for apps](https://brvneucore.herokuapp.com/application-api.json))

## Overview

Objectives
- Manage alliance specific groups for players.
- Provide an API to query these groups.

This project consists of two applications, the backend and the frontend.
For more information, see the Readme for 
[**Frontend**](frontend/README.md) and [**Backend**](backend/README.md).

More documentation is available in the `doc` directory:
[**Documentation**](doc/documentation.md), [**API**](doc/API.md).

## Installation

### EVE API setup

- visit https://developers.eveonline.com/applications or https://developers.testeveonline.com
- create a new application (eg: Brave Core DEV)
- Connection Type: "Authentication & API Access", add the required scopes. Scopes for the Core backend
  are configured with the environment variable BRAVECORE_EVE_SCOPES.
- set the callback to https://localhost/login-callback (change domain/port as required)

### App setup

Clone the repository or download the distribution (the distribution does not require Composer, Node.js or Java).

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Make sure that the web server can write in `backend/var/logs`.

In `dev` mode both the web server and SSH user write the same files to `backend/var/cache`,
so make sure they can override each other's files, e. g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories).

##### Distribution

If you are using the distribution, you only need to apply the database migrations:

```
cd backend
vendor/bin/doctrine-migrations migrations:migrate --no-interaction
```

##### Git

If you have cloned the repository, you must install the dependencies and build the backend and frontend:

`./install.sh` or

`./install.sh prod`

#### Cron jobs

Set up necessary cron jobs, e.g. 3 times daily with flock (adjust user and paths):
```
0 4,12,20 * * * neucore /usr/bin/flock -n /tmp/neucore-jobs.lockfile backend/bin/run-jobs.sh
```

### Local dev Requirements

* PHP 7.1+ with Composer, see `backend/composer.json` for necessary extensions
* Node.js 8 or 10 + npm (use npm 5.6.0 with node 8 if there are "write after end" errors)
* MariaDB or MySQL Server
* Apache or another HTTP Server
    * Set the document root to the `web` directory.
    * A sample Apache configuration in included in the [Vagrantfile](Vagrantfile) file and there 
      is a [.htaccess](web/.htaccess) file in the web directory.
    * For Nginx config see https://www.slimframework.com/docs/v3/start/web-servers.html#nginx-configuration,
      but rewrite to app.php instead of index.php.
* Java (only for swagger-codegen)

### Using Vagrant

Only tested with Vagrant 2 + libvirt.

- `vagrant up` creates and configures the virtual machine.
- If the Vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

Please note that the `rsync` that is used is a one way sync from the host to the virtual
machine which is done every time `vagrant up` or `vagrant reload` is executed.

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

## Deploy on Heroku

- Create a new app
- Add a compatible database, e. g. JawsDB Maria.
- Add the necessary Config Vars (see `backend/.env.dist` file)
- Add build packs in this order:

```
heroku buildpacks:add heroku/java
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

Logs are streamed to `stderr` instead of being written to files.
