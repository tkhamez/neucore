[![Build Status](https://travis-ci.com/tkhamez/neucore.svg?branch=master)](https://travis-ci.com/tkhamez/neucore)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=brvneucore&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=brvneucore)

# Neucore

An application for managing access for [EVE Online](https://www.eveonline.com/) players to external services 
of an alliance.

## Table of Contents

<!-- toc -->

- [Overview](#overview)
- [Installation](#installation)
  * [EVE API Setup](#eve-api-setup)
  * [App Setup](#app-setup)
    + [Server Requirements](#server-requirements)
    + [Install/Update](#installupdate)
      - [Archive file](#archive-file)
      - [Git](#git)
    + [Cron Job](#cron-job)
  * [First login and Customization](#first-login-and-customization)
- [Other Installation Methods](#other-installation-methods)
  * [Using Vagrant](#using-vagrant)
  * [Using Docker](#using-docker)
  * [Deploy on Heroku](#deploy-on-heroku)
  * [Deploy on AWS Beanstalk](#deploy-on-aws-beanstalk)
- [Final Notes](#final-notes)
  * [Origin](#origin)
  * [Related Software](#related-software)
  * [Contact](#contact)

<!-- tocstop -->

## Overview

Objectives:

- Management of groups for players.
- An API for applications to read these groups (and more).
- Access to [ESI](http://esi.evetech.net) data of all members.
- Member tracking and watch lists.
- Login via EVE SSO.

For more information, see the `doc` directory, including [**Documentation**](doc/documentation.md), 
an [**API**](doc/API.md) overview, and some [screenshots](doc/screenshots).

This project consists of two applications, the [**Backend**](backend/README.md) 
and the [**Frontend**](frontend/README.md).

A preview/demo installation is available at https://neucore.herokuapp.com.

## Installation

### EVE API Setup

- Visit https://developers.eveonline.com or https://developers.testeveonline.com
- Create a new application (e.g.: Neucore DEV)
- Connection Type: "Authentication & API Access", add the required scopes. Scopes for the backend
  are configured with the environment variable BRAVECORE_EVE_SCOPES. To use the "auto-whitelist"
  feature for the Watchlist, the scopes must include `esi-corporations.read_corporation_membership.v1`.
- Set the callback to https://your.domain/login-callback

### App Setup

#### Server Requirements

* PHP >=7.2.0 with Composer, see `backend/composer.json` for necessary extensions
* Node.js >=10.13.0 with npm >=6.4.1 (only tested with node v10 and v12)
* MariaDB or MySQL Server (tested with MySQL 5.7, 8.0 and MariaDB 10.2, 10.3, 10.4).  
  Unit tests can also be run using a SQLite in-memory database, but migration files work with MySQL/MariaDB only.
* Apache or another HTTP Server
    * Set the document root to the `web` directory.
    * A sample Apache configuration is included in the [Vagrantfile](Vagrantfile) file and there 
      is a [.htaccess](web/.htaccess) file in the `web` directory.
    * A sample [Nginx configuration](doc/docker-nginx.conf) file can be found in the `doc` directory.
* Java 8+ runtime (only for openapi-generator)

If your're using the pre-build releases, you only need PHP, a Database and a HTTP Server (not Composer,
Node.js or Java).

#### Install/Update

Clone the repository or [download](https://github.com/tkhamez/neucore/releases) the distribution 
(the distribution does not require Composer, Node.js or Java).

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Make sure that the web server can write to the log and cache directories, by default 
`backend/var/logs` and `backend/var/cache`.

Please note that both the web server and console user write the same files to the cache directory,
so make sure they can override each other's files, e. g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories).

If available, the app uses the APCu cache in production mode. This must be cleared during an update
(depending on the configuration, restart the web server or php-fpm).

##### Archive file

If you downloaded the .tar.gz file, you only need to run the database migrations and seeds and clear the cache.

If you are using a different cache directory, you must first copy or generate the Doctrine proxy cache files:
```
cp -R backend/var/cache/proxies /path/to/your/cache/proxies
# or
cd backend
vendor/bin/doctrine orm:generate-proxies
```

Then execute (adjust cache path if necessary)
```
cd backend
rm -rf var/cache/di
vendor/bin/doctrine-migrations migrations:migrate --no-interaction
bin/console doctrine-fixtures-load
```

##### Git

If you have cloned the repository, you must install the dependencies and build the backend and frontend:
```
./install.sh
# or
./install.sh prod
```

#### Cron Job

Set up necessary cron jobs, e. g. update characters every 2 hours and the rest 3 times daily 
using a lock file (adjust user and paths):

```
0 0,2,6,8,10,14,16,18,22 * * * neucore /var/www/neucore/backend/bin/console update-chars --log --hide-details
0 4,12,20 * * * neucore /usr/bin/flock -n /tmp/neucore-run-jobs.lock /var/www/neucore/backend/bin/run-jobs.sh
```

The output is logged to backend/var/logs.

### First login and Customization

Read the backend documentation on how to [make yourself an admin](backend/README.md#making-yourself-an-admin),
then you can navigate to "Admin" -> "Settings" and change texts, links and images that are specific to your 
installation.

## Other Installation Methods

### Using Vagrant

Only tested with Vagrant 2 + libvirt.

- `vagrant up` creates and configures the virtual machine.
- If the Vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

Please note that the `rsync` synchronization method used is a one-way synchronization from host to virtual 
machine that is performed each time `vagrant up` or `vagrant reload` is executed.
See https://www.vagrantup.com/docs/synced-folders for other methods. 

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

### Using Docker

Create the `backend/.env` file.  
Environment variables defined in `docker-compose.yml` have priority over `backend/.env`.

Execute the following to start the containers and build the app:
```sh
# rebuild if necessary
$ docker-compose build

# start services
$ export UID
$ docker-compose up -d

# Install backend and generate OpenAPI files
$ docker-compose run composer install --ignore-platform-reqs
$ docker-compose run composer composer openapi

# Generate OpenAPI JavaScript client
$ docker-compose run java /app/frontend/openapi.sh

# Build OpenAPI JS client
docker-compose run node npm install --prefix /app/frontend/neucore-js-client
docker-compose run node npm run build --prefix /app/frontend/neucore-js-client

# Build frontend
$ docker-compose run node npm install
$ docker-compose run node npm run build

# Update the database schema and seed data
$ docker-compose run php-cli vendor/bin/doctrine-migrations migrations:migrate --no-interaction
$ docker-compose run php-cli bin/console doctrine-fixtures-load
```

Browse to http://localhost:8080

Create database for unit tests
```
$ docker exec neucore_db sh -c 'mysql -e "CREATE DATABASE IF NOT EXISTS neucore_test" -pneucore'
$ docker exec neucore_db sh -c 'mysql -e "GRANT ALL PRIVILEGES ON neucore_test.* TO neucore@\"%\" IDENTIFIED BY \"neucore\"" -pneucore'
```

Run tests and other commands in the php-cli container: 
```
$ docker-compose run php-cli /bin/bash
```

Stop containers: 
```
docker-compose stop
```

Known problems:
- Unit tests that need the database don't work: "Aborted connection to db" errors.

### Deploy on Heroku

You can deploy the application on a free [Heroku](https://www.heroku.com) account.

- Create a new app
- Add a compatible database, e. g. JawsDB Maria.
- Add the necessary config vars (see `backend/.env.dist` file) and set the following:
  - BRAVECORE_LOG_PATH=php://stderr
- Add build packs in this order:

```
heroku buildpacks:add heroku/java
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

### Deploy on AWS Beanstalk

- Add an IAM user with Policy "AWSElasticBeanstalkFullAccess"
- Create a database (RDS)
- Create app environment:
    ```
    eb init -i
    eb create neucore-dev
    ```
- Add a security group for the database that includes the new environment
- Add a database for Neucore
- Add environment Variables (BRAVECORE_APP_ENV, BRAVECORE_DATABASE_URL etc.)
- Deploy again: `eb deploy`

See also [bravecollective/neucore-beanstalk](https://github.com/bravecollective/neucore-beanstalk) 
for an example of how to deploy the pre-build releases.

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
