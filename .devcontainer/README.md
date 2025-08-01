# InvoiceShelf Development Environment

This is dockerized development environment that allows developers to easily get started to develop InvoiceShelf.

This development environment is **NOT MEANT TO BE USED IN PRODUCTION** and is preconfigured with all the needed tools that InvoiceShelf requires for development purposes. It works on Windows, Linux and MacOS.

For production grade docker image, please refer to [InvoiceShelf/docker](https://github.com/InvoiceShelf/docker) and [InvoiceShelf on DockerHub](https://hub.docker.com/r/invoiceshelf/invoiceshelf).

## How to set up

## Pre-requisites
- [Docker](https://docs.docker.com/engine/install/)
- [IDE supporting devcontainer](https://containers.dev/supporting)

### Clone the project

Clone the InvoiceShelf project directly from InvoiceShelf git or your forked repository:

```bash 
git clone git@github.com:InvoiceShelf/InvoiceShelf.git
```

phew, ok, the difficoult part is over 😅.

## Development Container Workflow

As part of the new workflow, you only need to have Docker installed on your host system. This greatly simplifies your environment setup and allows you to quickly spin up the project environment at will.

The way it works is as follows: we use your IDE's integrated or extended feature called "Dev Container." The Dev Container is a predefined environment that encapsulates all the dependencies, tools, and configurations required for your project. This means that you can work in a consistent environment, regardless of the underlying system on which your code is running.

When you open your project in the IDE, it _should_ automatically detects the Dev Container configuration and sets up the environment accordingly. This includes installing necessary packages, configuring settings, and ensuring that the correct versions of tools are available.

To start simply run this command in the project root dir, then re-open the project with the IDE so the `devcontainer.json` config file get's detected:

```sh
cp .devcontainer/devcontainer.recomended.json .devcontainer/devcontainer.json
```

### (optional) `.devcontainer.json` adjustment

By default the SQLite container will be used for development, as it is by far the simplest to quickly spin up and tear down, if for one reason or another you wish to work with another DB you have predefined templates below, simply replace the

```json
      "dockerComposeFile": "docker-compose.sqlite.yml",
```

with the database flavour of your choice:

| Database          | Compose File                |
|-------------------|-----------------------------|
| SQLite3 (default) | `docker-compose.sqlite.yml` |
| MariaDB           | `docker-compose.mysql.yml`  |
| PostgresSQL       | `docker-compose.pgsql.yml`  |

## Spinning Up


### 2. Spinning Down

To **spin down** the environment, run docker compose as follows:

```
docker compose -f .dev/docker-compose.mysql.yml down
```

### 3. Working with binaries

To correctly run `composer`, `npm`, `artisan`, `pint`, `pest` or other binaries within this project, you must ssh into the container as follows:

```
docker exec -it --user invoiceshelf invoiceshelf-dev-php /bin/bash
```

In the `/home/invoiceshelf/app` directory you can find the application root and run the commands from there.

## What is included

### 1. Web Server

This dockerized environment uses PHP-FPM and NGINX together to serve the website `invoiceshelf.test`

Both NGINX and PHP-FPM are configured with optimal settings for development. Please don't use this in production.

**URL**: http://invoiceshelf.test/

### 2. Databases

This dockerized environment comes with support for all three databases that InvoiceShelf suppots: MySQL, PostgreSQL and SQLite.

The setup parameters/credentials for each of the supported databases are as follows.

|             | MySQL         | PostgreSQL   | SQLite |
|-------------|:-------------:|:------------:|:------:|
| **DB_USER** | invoiceshelf  | invoiceshelf |        |
| **DB_PASS** | invoiceshelf  | invoiceshelf |        |
| **DB_NAME** | invoiceshelf  | invoiceshelf | /home/invoiceshelf/database/database.sqlite  |
| **DB_HOST** | mysql         | pgsql        |        |
| **DB_PORT** | 3036          | 5432         |        |

**Note:** The only required field for SQLite is **DB_NAME**.

### 3. Adminer

Adminer is UI tool for viewing the database contents and executing queries.

It supports MySQL, PostgreSQL, SQLite.

**URL**: http://invoiceshelf.test:8080

#### MySQL/PostgresSQL

To log into the MySQL or PostgresSQL, use the database information specified in the above section (2. Databases)

#### SQLite

To log into the SQLite, use the following credentials:

| KEY          | VALUE                     |
|--------------|---------------------------|
| **USERNAME** | admin                     |
| **PASSWORD** | admin                     |
| **DATABASE** | /database/database.sqlite |


### 4. Mailpit (fake mail)

To utilize Mailpit, use the following credentials:

| KEY                 | VALUE       |
|---------------------|-------------|
| **MAIL DRIVER**     | smtp        |
| **MAIL HOST**       | mail        |
| **MAIL PORT**       | 1025        |
| **MAIL ENCRYPTION** | none        |
| **MAIL USER**       | leave empty |
| **MAIL PASS**       | leave empty |
| **FROM MAIL ADDR**  | your choice |
| **FROM MAIL NAME**  | your choice |


**URL**: http://invoiceshelf.test:8025

---

If you have any questions, feel free to open issue.
