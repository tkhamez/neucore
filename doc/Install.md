# Installation

<!-- toc -->

- [EVE API Setup](#eve-api-setup)
- [Application Setup](#application-setup)
  * [Run Docker Image](#run-docker-image)
  * [Manual Installation](#manual-installation)
  * [Docker Development Environment](#docker-development-environment)
  * [Deploy on Heroku](#deploy-on-heroku)
  * [Deploy on AWS Beanstalk](#deploy-on-aws-beanstalk)
- [Post Installation](#post-installation)
  * [Cronjob](#cronjob)
  * [Customization](#customization)
  * [Security](#security)
- [Build Distribution](#build-distribution)

<!-- tocstop -->

## EVE API Setup

- Visit https://developers.eveonline.com and create a new application.
- Connection Type: "Authentication & API Access", add the required scopes. Scopes for the backend
  are configured with the environment variable NEUCORE_EVE_SCOPES. To use the "auto-allowlist"
  feature for the Watchlist, the scopes must include `esi-corporations.read_corporation_membership.v1`.
- Set the callback to `https://your.domain/login-callback`.

## Application Setup

Below are several alternatives for running Neucore.

All installation methods share the same configuration via environment variables, see [.env.dist](../backend/.env.dist).

### Run Docker Image

You can run Neucore using the [Docker](https://www.docker.com/) image from 
https://hub.docker.com/r/tkhamez/neucore.

First start a database, for example:

```shell
docker network create neucore_prod

docker run \
  --volume="$(pwd)/docker-db":/var/lib/mysql \
  --env=MARIADB_USER=neucore \
  --env=MARIADB_PASSWORD=neucore \
  --env=MARIADB_DATABASE=neucore \
  --env=MARIADB_ROOT_PASSWORD=neucore \
  --network=neucore_prod \
  --name=neucore_db_prod \
  --detach=true \
  --rm \
  mariadb:10.6

# to stop it again:
docker stop neucore_db_prod
```

Next, start Neucore (adjust EVE client ID and secret):

```shell
docker run \
  --env=NEUCORE_APP_ENV=prod \
  --env=NEUCORE_DATABASE_URL=mysql://neucore:neucore@neucore_db_prod/neucore \
  --env=NEUCORE_EVE_CALLBACK_URL=http://localhost:8080/login-callback \
  --env=NEUCORE_EVE_CLIENT_ID=123 \
  --env=NEUCORE_EVE_SECRET_KEY=abc \
  --env=NEUCORE_EVE_SCOPES="esi-corporations.read_corporation_membership.v1" \
  --env=NEUCORE_SESSION_SECURE=0 \
  --workdir=/var/www/backend \
  --publish=127.0.0.1:8080:80 \
  --network=neucore_prod \
  --name=neucore_prod_http \
  --rm \
  tkhamez/neucore
```

Then create the database schema and add the initial data:

```shell
docker exec -u www-data neucore_prod_http vendor/bin/doctrine-migrations migrations:migrate --no-interaction
docker exec -u www-data neucore_prod_http bin/console doctrine-fixtures-load
```

Now login at http://localhost:8080/ and then make yourself an admin:

```shell
docker exec -u www-data neucore_prod_http bin/console make-admin 1
```

#### Further Configuration

To access the database from the host, add the following argument when running the database container, for example:

```
  --publish=127.0.0.1:33060:3306 \
```

If you are not using a database via Docker, you can remove the `--network` argument (and obviously change 
NEUCORE_DATABASE_URL).

To store the logs on the host, create a directory, change its permission, and add the following argument
when running the Neucore container, for example:

```shell
mkdir docker-logs && sudo chown 33 docker-logs
```

```
  --volume="$(pwd)/docker-logs":/var/www/backend/var/logs \
```

To use a custom [theme.js](../frontend/public/theme.js) file or another favicon.ico, add the following arguments, 
for example:

```
  --volume="$(pwd)/theme.js":/var/www/html/dist/theme.js \
  --volume="$(pwd)/favicon.ico":/var/www/html/favicon.ico \
```

To add a service plugin, for example the [Discord Plugin](https://github.com/tkhamez/neucore-discord-plugin), add
the following arguments, for example:

```
  --volume=$(pwd)/neucore-discord-plugin:/var/www/plugins/discord \
  --env=NEUCORE_DISCORD_PLUGIN_DB_DSN="mysql:dbname=neucore_discord;host=192.168.1.2" \
  --env=NEUCORE_DISCORD_PLUGIN_DB_USERNAME=neucore \
  --env=NEUCORE_DISCORD_PLUGIN_DB_PASSWORD=neucore \
```

In a real production environment you want to set up a reverse proxy server with SSL and remove the 
`NEUCORE_SESSION_SECURE=0` environment variable. Also, remove `--rm` and add instead:

```
  --restart=always \
  --detach=true \
```

#### Create the Image

You can also create the image yourself. Clone the repository and build a distribution (see below) or 
[download](https://github.com/tkhamez/neucore/releases) it and place it in the subdirectory `dist`
(create it if it doesn't exist). Make sure there is only one `neucore-*.tar.gz` file. Then execute 
the following:

```shell
docker build --no-cache -t neucore .
```

### Manual Installation

#### Server Requirements

A Linux server (others may work, but were not tested).

To run the application:
* PHP >=7.4.0 (64bit version), see [backend/composer.json](../backend/composer.json) for necessary and suggested extensions (APCu highly
  recommended).
* MariaDB or MySQL Server (tested with MySQL 8.0 and MariaDB 10.2, 10.6 and 10.8). (Unit tests can also be run using 
  a SQLite in-memory database.)
* An HTTP Server with support for PHP and URL rewriting.
  * Set the document root to the `web` directory.
  * Configure URL rewriting to `app.php`:
    * For Apache there's a [.htaccess](../web/.htaccess) file in the `web` directory (set `AllowOverride All` 
      in your VirtualHost configuration for that directory if you want to use it).
    * For Nginx there's a sample [configuration](docker-nginx.conf) file in the `doc` directory.

Additionally, for a development environment and to build the application:
* PHP extensions: ast (optional for phan), pdo_sqlite (optional for unit tests), xdebug (optional for debugging)
* Composer 2.
* Node.js, only tested with version 16.15.1 with npm 8.11.0.
* Java runtime >=8 (but only tested with v11, v17) to generate the OpenAPI JavaScript client.

#### Install/Update

Clone the repository or [download](https://github.com/tkhamez/neucore/releases) the pre-built distribution
file and extract it.

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Make sure that the web server can write to the log and cache directories, by default
`backend/var/logs` and `backend/var/cache`.

Please note that both the web server and console user write the same files to the cache directory,
so make sure they can override each other's files, e.g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories), or simply use the same user.

If available, the app uses an APCu cache in production mode. It must be cleared during an update:
depending on the setup, restart the web server or php-fpm.

##### Pre-built Distribution file

If you downloaded the pre-built app, you only need to run the database migrations and seeds and clear the cache.

If you are using a different cache directory, you must first copy or generate the Doctrine proxy cache files:
```
cp -R backend/var/cache/proxies /path/to/your/cache/proxies
# or
cd backend
bin/doctrine orm:generate-proxies
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
# for production:
./install.sh prod

# for develeopment:
./install.sh
cd frontend && npm run build
```

### Docker Development Environment

Only tested on Linux and once on macOS.

Copy `backend/.env.dist` file to `backend/.env` and adjust values, the database password and user are both `neucore`,
the database host is `neucore_db` and the database name also `neucore`.

- Build the containers with  
  `export UID && docker-compose build`
- Start services:  
  `export UID && docker-compose up`
- Install the app:  
  `export UID && ./install-docker.sh`  
  `docker-compose run neucore_node npm run build`
- Run tests and other commands in the php and node containers:  
  `export UID && docker-compose exec neucore_php /bin/sh`  
  `export UID && docker-compose run --service-ports neucore_node /bin/sh`

The web application is available at http://localhost:8080, the frontend development server at http://localhost:3000.
The database is also available at `127.0.0.1:30306` and it's data is stored in the `.db` subdirectory.

### Deploy on Heroku

You can deploy the application on a free [Heroku](https://www.heroku.com) account.

- Create a new app
- Add a compatible database, e.g. JawsDB Maria.
- Add the necessary config vars (see `backend/.env.dist` file) and set the following:
  - NEUCORE_LOG_PATH=php://stderr
  - NEUCORE_CACHE_DIR=/tmp
- Add build packs in this order:
  ```
  heroku buildpacks:add heroku/java
  heroku buildpacks:add heroku/nodejs
  heroku buildpacks:add heroku/php
  ```

### Deploy on AWS Beanstalk

See [bravecollective/neucore-beanstalk](https://github.com/bravecollective/neucore-beanstalk) for an example.

## Post Installation

### Cronjob

Set up necessary cron jobs, e.g. every 8 hours using a lock file (adjust user and paths):
```
0 4,12,20 * * * neucore /usr/bin/flock -n /tmp/neucore-run-jobs.lock /var/www/neucore/backend/bin/run-jobs.sh
```

The output is logged to backend/var/logs.

### Customization

Adjust `web/dist/theme.js` if you want another default theme or add additional JavaScript code, e.g. for
analytics software. 

Replace the favicon icon in `web/favicon.ico` if you want to use a different one.

### Security

It is recommended to set the following security related HTTP headers in the web server configuration:

```
Strict-Transport-Security "max-age=31536000"
Content-Security-Policy "default-src 'self'; script-src 'self' data:; font-src 'self' data:; img-src 'self' data: https://images.evetech.net; connect-src 'self' https://esi.evetech.net; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; sandbox allow-downloads allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation;"
X-Frame-Options "sameorigin"
X-Content-Type-Options "nosniff"
```

## Build Distribution

There are scripts that build the distribution package, `dist.sh` or `dist-docker.sh`. They need a 
working development environment.
