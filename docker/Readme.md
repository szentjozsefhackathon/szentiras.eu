> [!IMPORTANT]
As of milestone Szentiras.eu the supported development environment is based on docker compose.

# Environments with docker compose

## Local development environment

The `docker-compose.yml` provides a local development environment.
The idea is that you can have a working development environment, set up for effective development with commands: one to create the .env file (a sensible example is provided), and one to fire up the containers.

It something is not right for you, don't hesitate to create an issue.

It won't start up without a proper `.env` file and a `.env.docker` file (latter contains customizations for the built containers). So first

```
cp .env.local.dist .env && cp .env.docker.example .env.docker
```
Make adjustments if needed. Then

Start the stack: 

```
docker compose up -d
```

### The services

#### app

It build a webserver that based on the official `php` docker image with apache and 8.2 php version.
You can find the details in the `Dockerfile.dev`.

It mounts the whole code under the `/var/www/html`, so what you modified that appear in the local server.

The app is reachable at the `http://localhost:8080` url.

#### database

It's a `pgvector` instance. The database folder in a named volume: `db-data`. You can zap it with `docker compose down -v` command. Notice the `-v` parameter.

The database seed at the first run with the dump in the `tmp` folder. You can put there another dump, sql scripts as you can read it the 

The hostname of the service in the docker network is `database`, be careful to use it in the .env files.

The sql port expose to localhost, so you can use any mysql client in the localhost at 5432 (or what you set) port.

#### database_testing

The database used for running the tests.

#### sphinx

Sphinxsearch indexer. The config files are in `deploy` folder, there are `__ENV_VAR__` placehoders in them.
The `docker/sphinx/start.sh` changes the placeholders to the environment variable's vaules, initializes the index files.
In `dev` environment the folder of data files mounted into the container in `production` environment this folder is persisted to a named volume to avoid loss between container restarts.

#### mailpit

A fancy SMTP mail catcher with mail format analyser, and with API for easy testing.

The smtp port is "localhost:1025" or in the docker network: "mailpit:1025". The web ui at "http://localhost:8025".

#### composer

It installs the php dependencies with the composer.phar that's in the repo root folder.
Changing the composer.json you should run the service again to install/update the php dependencies.

It runs and exit.

#### migrator

Makes the migrations on the database in the starting process. We can also use this cache warm-up and other initial processes.

#### migrator_testing

Initializes the testing database.

#### The starting order

With the `depends_on` keywords we can controll the order of the starting process.

**Independent services**

1. The **app_dev_prepopulate** service creates some files to ease development.
3. Start the **database** with the initializaton.
   When the database finish the init process they status will be healthy.
4. The **composer** install the php dependecies. The vendor folder created in the project folder (the .gitignore responsible to not appear in the version control)

**Dependent services**

- The **app** has a dependency on **app_dev_prepopulate**, that creates some helper files for development.
- The **composer** service makes the `php composer install`. In development this needs the **database** to generate model helper classes.
- The **migrator** runs when the database is healthy and the composer exits without any errors. It runs the migrations on the database.
- The **sphinx** started when the database is healthy and the migrator exits without any errors. It starts up the full text search engine/
- The **app** started when the database is healthy and the migrator exits without any errors.

### Exposed ports

(Some of them can be reconfigured in `.env.local.docker`, see `docker-compose.yml`)

| service | protocol | port/url | service |
| -- | -- | -- | -- |
| app            | http     | 8080 | The web service
| app            | http     | 9003 | Xdebug, to let the browser initiate debugging |
| app            | http     | 5173 | vite, to allow the browser cooperate |
| database       | postgresql    | 5432 | The database, to let you look into it with e.g. DBeaver


## Testing the production image

The `.env.prod` file is mapped to `env_file` in docker-compose, so they are used as environment variables, and it is not intended to log in the container and change .env file there (there is actually no .env file in the production container).
Note that in `config/*.php` configurations there should be defaults for most variables. All keys, credentials etc. should be in secrets or set by environment variables on the server. That's also true for other variables. 
However having an `.env.prod` file as below is for easing testing the production build locally.

```
cp .env.prod.dist .env.prod
php artisan key:generate --show
```
And copy the shown key to `.env.prod`, and also add the database credentials, and else what you need.

Then

```
docker compose -f docker-compose.prod.yml up -d
```


# [Deprecated] docker/Dockerfile (one box to rule them all)

## Build the image
Run this from the `<szentiras-repo-root>` folder.

```sh
docker build --build-arg UID=$(id -u) --build-arg GID=$(id -g) -t szentiras-dev . -f docker/Dockerfile
```

Your local UID and GID need to be propagated to the image.

# Start the image the first time

This is just for the first start (initialization). Be sure to run this from the Szentiras repo root.

```sh
docker run -it --name szentiras-dev -v "$(pwd):/app" --net=host szentiras-dev

source docker/init.sh
```

# Use the image

```sh
docker start -ai szentiras-dev
```

Then, in the Docker interactive shell session, you may have to start the MySQL server:

```sh
service mysql start
```

Then, you need to start the indexer service:

```
service sphinxsearch start
```

To serve the website:

```sh
php artisan serve --port 1024
```

To "open a second terminal" to this Docker container:

```sh
docker exec -it szentiras-dev /bin/bash
```

To connect to the database setting the right character encoding:

```sh
mysql -u homestead -p
# password: secret
SET character_set_client = 'utf8mb4';
SET character_set_connection = 'utf8mb4';
SET character_set_results = 'utf8mb4';
```

To reindex:

```sh
indexer --config /etc/sphinxsearch/sphinx.conf --all --rotate
```

# Why this version of Ubuntu?

Because for this version, Python2 was still available (needed by something else :).
