# Installation

## Vagrant Requirements

For synced folder with NFS (instead of rsync), install nfs-kernel-server and edit Vagrantfile:
```
- config.vm.synced_folder "./", "/var/www/bravecore"
+ config.vm.synced_folder "./", "/var/www/bravecore", :nfs => true
```

- `vagrant up`
- browse to https://localhost
- If the vagrant file changes, run `vagrant provision` to update the VM.
- `vagrant destroy` will completely remove the VM.

## Local dev Requirements

- PHP with Composer (see Vagrantfile necessary additional extensions)
- Node.js + npm
- MySQL/MariaDB
- Apache (dev should also works with PHP's build-in server)

Set the webserver's document root to the "web" directory.

## EVE API setup

- visit https://developers.eveonline.com/applications
- create a new application (eg: brvneucore-dev)
- TODO document list of required permissions here for authentication & api access
- set the callback to https://localhost/api/user/auth/callback

## Install dev

Copy `.env.dist` file to `.env` and adjust values.

Install dependencies and build backend and frontend:
```
./install.sh
```

## Install prod

Set the required environment variables, see in file `.env.dist`

Make sure that the webserver can write in var/logs and var/cache.

Execute:
```
./install.sh prod
```

## Heroku

To deploy to Heroku, add buildpacks in this order:
```
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```
