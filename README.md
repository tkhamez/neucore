# Brave Collective Core Services Prototype

[![Build Status](https://api.travis-ci.org/tkhamez/brvneucore.svg?branch=master)](https://travis-ci.org/tkhamez/brvneucore)
[![codecov](https://codecov.io/gh/tkhamez/brvneucore/branch/master/graph/badge.svg)](https://codecov.io/gh/tkhamez/brvneucore)

https://brvneucore.herokuapp.com

API: https://brvneucore.herokuapp.com/api

## Installation

### Vagrant Requirements

For synced folder with NFS (instead of rsync), install nfs-kernel-server and edit Vagrantfile:
```
- config.vm.synced_folder "./", "/var/www/bravecore"
+ config.vm.synced_folder "./", "/var/www/bravecore", :nfs => true
```

- `vagrant up`
- browse to https://localhost
- If the vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

The Vagrant setup will create the file `backend/.env` with correct values for the database connection.
The values for the EVE application must be adjusted.

### EVE API setup

- visit https://developers.eveonline.com/applications
- create a new application (eg: brvneucore-dev)
- TODO document list of required permissions here for authentication & api access
- set the callback to https://localhost/api/user/auth/callback

### Local dev Requirements

- PHP with Composer (see Vagrantfile for necessary additional extensions)
- Node.js + npm
- MySQL/MariaDB
- Apache (dev should also works with PHP's build-in server)

Set the webserver's document root to the "web" directory.

### Install dev

Copy `backend/.env.dist` file to `backend/.env` and adjust values.

Install dependencies and build backend and frontend:
```
./install.sh
```

### Install prod

Set the required environment variables, see in file `backend/.env.dist`

Make sure that the webserver can write in var/logs and var/cache.

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
