# Installation

<!-- toc -->

- [EVE API Setup](#eve-api-setup)
- [Application Setup](#application-setup)
  * [Run Docker Image](#run-docker-image)
  * [Manual Installation](#manual-installation)
  * [Docker Development Environment](#docker-development-environment)
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

This is how you install Docker on Ubuntu 22.04:

```
sudo apt install docker.io
sudo usermod -a -G docker user
sudo systemctl enable docker
```

In the second line replace "user" with your username, after that login again.

If you don't have a database you can also use Docker to create one, for example:

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
  --restart=always \
  --detach=true \
  mariadb:10.11

# to stop it again:
docker stop neucore_db_prod
```

Next, start Neucore (adjust EVE client ID and secret):

```shell
docker run \
  --env=NEUCORE_APP_ENV=prod \
  --env=NEUCORE_DATABASE_URL="mysql://neucore:neucore@neucore_db_prod/neucore" \
  --env=NEUCORE_EVE_CALLBACK_URL="http://localhost:8080/login-callback" \
  --env=NEUCORE_EVE_CLIENT_ID=123 \
  --env=NEUCORE_EVE_SECRET_KEY=abc \
  --env=NEUCORE_EVE_SCOPES="esi-corporations.read_corporation_membership.v1" \
  --env=NEUCORE_SESSION_SECURE=0 \
  --workdir=/var/www/backend \
  --publish=8080:80 \
  --network=neucore_prod \
  --name=neucore_prod_http \
  --restart=always \
  --detach=true \
  tkhamez/neucore
```

The above will automatically start the container when the server is started if the Docker daemon is running.
The application will be available on port 8080, e.g. http://localhost:8080.

This is how you stop and restart the container or remove it completely:

```shell
# stop and restart:
docker stop neucore_prod_http
docker start neucore_prod_http

# Remove the container to be able to use "docker run" again
docker rm neucore_prod_http
```

Then create the database schema and add the initial data:

```shell
docker exec -u www-data neucore_prod_http vendor/bin/doctrine-migrations migrations:migrate --no-interaction
docker exec -u www-data neucore_prod_http bin/console doctrine-fixtures-load
```

Now login at http://localhost:8080/ (replace localhost if you IP address if you run it on a remote host), 
then make yourself an admin:

```shell
docker exec -u www-data neucore_prod_http bin/console make-admin 1
```

Continue reading [Getting started](../README.md#getting-started).

#### Production environment

In a production environment you want to run a web server with SSL and remove the `NEUCORE_SESSION_SECURE=0` 
environment variable.

You can do that by setting up a reverse proxy (recommended) or by forwarding the SSL port from the Docker 
container and provide an SSL certificate.

To use SSL from Docker use the following arguments when running the container:

```
  --volume="/path/to/your/certificate":/etc/ssl/certs/neucore.pem \
  --volume="/path/to/your/key":/etc/ssl/private/neucore.key \
  --publish=443:443 \
```

If you do not have a certificate you can remove those arguments, but there will be a certificate warning
from your browser.

The application will be available at e.g. https://localhost.

Example reverse proxy configuration for Apache, including necessary setup on Ubuntu 22.04:

```
sudo apt install apache2 certbot python3-certbot-apache
sudo a2enmod ssl proxy proxy_http
sudo a2ensite default-ssl

sudo nano /etc/apache2/sites-available/z-neucore.conf
  <VirtualHost *:443>
    ServerName neucore.tian-space.net
    ProxyPreserveHost On
    ProxyRequests off
    ProxyPass / http://localhost:8080/
    ProxyPassReverse / http://localhost:8080/
  </VirtualHost>

sudo a2ensite z-neucore
sudo certbot --apache

sudo systemctl restart apache2
```

Once the reverse proxy is working, you can change the "publish" argument of Docker so that the port is 
no longer available for every IP address:

```
--publish=127.0.0.1:8080:80 \
```

#### Further Configuration

To access the database from the host, add the following argument when running the database container, for example:

```
  --publish=127.0.0.1:33060:3306 \
```

If you are not using a database via Docker, you can remove the `--network` argument (and obviously change 
NEUCORE_DATABASE_URL).

To store the logs on the host, create a directory, change its permission, and add a "volume" argument
when running the Neucore container, for example:

```shell
mkdir docker-logs && sudo chown 33 docker-logs
```
```
  --volume="$(pwd)/docker-logs":/var/www/backend/var/logs \
```

To use a custom [theme.js](../frontend/public/theme.js) file, add the following argument, 
for example:

```
  --volume="$(pwd)/theme.js":/var/www/web/dist/theme.js \
```

To add a service plugin, for example the [Discord Plugin](https://github.com/tkhamez/neucore-discord-plugin), add
the following arguments (note: `$(pwd)/neucore-discord-plugin` is the path on the host where you stored
the plugin), for example:

```
  --volume=$(pwd)/neucore-discord-plugin:/var/www/plugins/discord \
  --env=NEUCORE_PLUGINS_INSTALL_DIR=/var/www/plugins \
  --env=NEUCORE_DISCORD_PLUGIN_DB_DSN="mysql:dbname=neucore_discord;host=192.168.1.2;user=neucore;password=neucore" \
```

#### Create the Image

You can also create the image yourself. Clone the repository and build a distribution (see below) or 
[download](https://github.com/tkhamez/neucore/releases) it and place it in the subdirectory `dist`
(create it if it doesn't exist). Make sure there is only one `neucore-*.tar.gz` file. Then execute 
the following:

```shell
docker build -f setup/Dockerfile --no-cache -t neucore dist
```

### Manual Installation

#### Server Requirements

A Linux server (others may work, but were not tested).

To run the application:
* PHP >=8.1.0 - 8.3 (64bit version), see [backend/composer.json](../backend/composer.json) for necessary and 
  suggested extensions (APCu highly recommended).
* MariaDB or MySQL Server (tested with MariaDB 10.5, 10.11, 11.4 and MySQL 8.0.22, 8.4.0, 
  NO_BACKSLASH_ESCAPES should not be on). Unit tests can also be run with a SQLite in-memory database.
* An HTTP Server with support for PHP and URL rewriting.
  * Set the document root to the `web` directory.
  * Configure URL rewriting to `app.php`:
    * For Apache there's a [.htaccess](../web/.htaccess) file in the `web` directory (set `AllowOverride All` 
      in your VirtualHost configuration for that directory if you want to use it).
    * For Nginx there's a sample [configuration](../setup/docker-nginx.conf) file in the `setup` directory.

Additionally, for a development environment and to build the application:
* PHP extensions: ast (optional for phan), pdo_sqlite (optional for unit tests), xdebug (optional for debugging).
* Composer 2.
* Node.js, only tested with version 18.12.1 (LTS) with npm 8.19.2.
* Java runtime >=11 (but only tested with v17) to generate the OpenAPI JavaScript client.

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
setup/install.sh prod

# for develeopment:
setup/install.sh
cd frontend && npm run build
```

### Docker Development Environment

Only tested on Linux and once or twice on macOS.

Copy `backend/.env.dist` file to `backend/.env` and adjust values, the database password and user are both `neucore`,
the database host is `neucore_db` and the database name also `neucore`.

- Always run `export UID` first in each console that you use to execute any of the following commands.
- Build the containers:  
  `docker-compose build`
- Start services:  
  `docker-compose up`
- Install the app:  
  `setup/install-docker.sh`  
  `docker-compose exec neucore_node npm run build`
- After the first login, make the account with the ID 1 admin:  
  `docker-compose exec neucore_php bin/console make-admin 1`
- Run tests and other commands in the php and node containers:  
  `docker-compose exec neucore_php /bin/sh`  
  `docker-compose exec neucore_node /bin/sh`

The web application is available at http://localhost:8080, the frontend development server at http://localhost:3000.
The database is also available at `127.0.0.1:30306` and it's data is stored in the `.db` subdirectory.

### Deploy on AWS Beanstalk

See [bravecollective/neucore-beanstalk](https://github.com/bravecollective/neucore-beanstalk) for an example.

## Post Installation

### Cronjob

Set up necessary cron jobs, e.g. every 8 hours using a lock file (adjust user and paths):
```
0 4,12,20 * * * neucore /usr/bin/flock -n /tmp/neucore-run-jobs.lock /var/www/neucore/backend/bin/run-jobs.sh
```

The output is logged to backend/var/logs. It can be filtered to see how long each job was running, e.g.:
```
cat app-cli-2022w36.log | grep -E 'Started|Finished|Guzzle' > app-cli-2022w36-filtered.log
```

### Customization

Adjust `web/dist/theme.js` if you want another default theme or add additional JavaScript code, e.g. for
analytics software. 

### Security

It is recommended to set the following security related HTTP headers in the web server configuration:

```
Strict-Transport-Security "max-age=31536000"
Content-Security-Policy "default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self' data:; img-src 'self' data: https://images.evetech.net; connect-src 'self' https://esi.evetech.net; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; sandbox allow-downloads allow-forms allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts allow-top-navigation;"
X-Frame-Options "sameorigin"
X-Content-Type-Options "nosniff"
```

## Build Distribution

There are scripts that build the distribution package, `setup/dist.sh` or `setup/dist-docker.sh`. They need a 
working development environment.
