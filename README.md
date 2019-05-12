[![Build Status](https://travis-ci.org/tkhamez/neucore.svg?branch=master)](https://travis-ci.org/tkhamez/neucore)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=brvneucore&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=brvneucore)

# Neucore

An application for managing access for [EVE Online](https://www.eveonline.com/) players to external services 
of an alliance.

## Overview

Objectives:

- Management of groups for players.
- An API for applications to read these groups (and more).
- Access to [ESI](http://esi.evetech.net) data of all members.
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
  are configured with the environment variable BRAVECORE_EVE_SCOPES.
- Set the callback to https://your.domain/login-callback

### App Setup

#### Server Requirements

* PHP 7.1+ with Composer, see `backend/composer.json` for necessary extensions
* Node.js with npm (tested with node 8.16, 10.15 and npm 6.4.1)
* MariaDB or MySQL Server (tested with MySQL 5.7, 8.0 and MariaDB 10.3)
* Apache or another HTTP Server
    * Set the document root to the `web` directory.
    * A sample Apache configuration is included in the [Vagrantfile](Vagrantfile) file and there 
      is a [.htaccess](web/.htaccess) file in the web directory.
    * A sample Nginx configuration can be found in the doc directory [nginx.conf](doc/nginx.conf)
* Java 8+ runtime (only for openapi-generator)

#### Install/Update

Clone the repository or [download](https://github.com/tkhamez/neucore/releases) the distribution 
(the distribution does not require Composer, Node.js or Java).

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Make sure that the web server can write in `backend/var/logs` and `backend/var/cache`.

Please note that both the web server and console user write the same files to `backend/var/cache`,
so make sure they can override each other's files, e. g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories).

If available, the app uses the APCu cache in production mode. This must be cleared during an update
(depending on the configuration, restart the web server or php-fpm).

##### Archive file

If you downloaded the .tar.gz file, you only need to run the database migrations and seeds and, 
depending on the update method, clear the cache:

```
cd backend
rm -rf var/cache/{di,http,proxies}
vendor/bin/doctrine-migrations migrations:migrate --no-interaction
bin/console doctrine-fixtures-load
```

##### Git

If you have cloned the repository, you must install the dependencies and build the backend and frontend:

`./install.sh` or

`./install.sh prod`

#### Cron Job

Set up necessary cron jobs, e. g. 3 times daily (adjust user and paths):

```
0 4,12,20 * * * neucore /var/app/backend/bin/run-jobs.sh
```

The output is logged to backend/var/logs.

### First login and Customization

Read the backend documentation on how to [make yourself an admin](backend/README.md#making-yourself-an-admin),
then you can navigate to "Admin" -> "Settings" and change texts, links and images that are specific to your 
installation.

### Using Vagrant

Only tested with Vagrant 2 + libvirt.

- `vagrant up` creates and configures the virtual machine.
- If the Vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

Please note that the `rsync` synchronization method used is a one-way synchronization from host to virtual 
machine that is performed each time `vagrant up` or `vagrant reload` is executed.

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

### Deploy on Heroku

You can deploy the application on a free [Heroku](https://www.heroku.com) account.

- Create a new app
- Add a compatible database, e. g. JawsDB Maria.
- Add the necessary config vars (see `backend/.env.dist` file)
- Add build packs in this order:

```
heroku buildpacks:add heroku/java
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

Logs are streamed to `stderr` instead of being written to files.

## Final notes

### Origin

The software was originally developed for the [Brave Collective](https://www.bravecollective.com), 
when CCP shut down the old API and we had to replace our Core system.

This is also where the name "Neucore" comes from.

### Related Software

Clients for the application API are available on the Brave Collective GitHub for PHP and Python:

- [neucore-api](https://github.com/bravecollective/neucore-api)
- [neucore-api-python](https://github.com/bravecollective/neucore-api-python)

### Contact

If you have any questions or feedback, you can contact Tian Khamez on [Tweetfleet Slack](https://tweetfleet.slack.com)
(get invites [here](https://www.fuzzwork.co.uk/tweetfleet-slack-invites/)).
