# InvoiceShelf Development Environment

This is dockerized development environment that allows developers to easily get started to develop InvoiceShelf.

This development environment is **NOT MEANT TO BE USED IN PRODUCTION** and is preconfigured with all the needed tools that InvoiceShelf requires for development purposes. It works on Windows, Linux and MacOS.

For production grade docker image, please refer to [InvoiceShelf/docker](https://github.com/InvoiceShelf/docker) and [InvoiceShelf on DockerHub](https://hub.docker.com/r/invoiceshelf/invoiceshelf).

## How to set up

### 1. Hosts configuration

We use `invoiceshelf.test` domain for local development within this environment and you need to adhere to it.

For that purpose you need to edit your OS hosts file or DNS server and add the following line to make the local domain name available on your system.

```
127.0.0.1 invoiceshelf.test
```

#### 1.1. Windows

The hosts file on Windows is located at `C:\Windows\system32\drivers\etc\hosts`.

You need to launch Notepad as administrator, open the file through **File > Open**, add the line from above and save the file.

#### 1.2. Linux/MacOS

The hosts file on Linux and Mac is located at `/etc/hosts`.

You need to open the file using your favorite editor as sudo/root, add the line from above and save the file.

### 2. FileSystem configuration (Linux)

If you are using **Linux**, you need to make sure that **USRID** and **GRPID** environment variables are set and matching your current session user ids. Those two variables are required to set up the filesystem permissions correctly on Linux.

You can run it one time, every time before starting as follows:

```
export USRID=$(id -u) && export GRPID=$(id -g)
```

or you can append this to your .zshrc/.bashrc by running this command in your terminal:

```
grep -qxF 'export USRID=$(id -u) GRPID=$(id -g)' ~/.${SHELL##*/}rc || echo 'export USRID=$(id -u) GRPID=$(id -g)' >> ~/.${SHELL##*/}rc
```
this will append the `export` line to your rc file and run it on each terminal session.

### 3. Clone the project

Clone the InvoiceShelf project directly from InvoiceShelf git or your forked repository:

```bash 
git clone git@github.com:InvoiceShelf/InvoiceShelf.git
```

## Development Workflow

We bundled separate docker-compose.yml file for each database: MySQL, PostgresSQL and SQLite, you can use any of those to spin up your development environment.

| Database | Compose File              |
|---------|---------------------------|
| SQLite3 | docker-compose.sqlite.yml |
| MariaDB | docker-compose.mysql.yml  |
| PostgresSQL | dpcler-compose.pgsql.yml  |

### 1. Spinning Up

To **spin up** the environment, run docker compose as follows:

**Important**: If you are on **Linux** and didn't add the `export` line to your .zshrc/.bashrc file, you need to repeat `step 2` before spinning up, otherwise you will face permissions issues.

```
docker compose -f .dev/docker-compose.mysql.yml up --build
```

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

|   | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| **DB_USER** | invoiceshelf  | invoiceshelf | Not applicable  |
| **DB_PASS** | invoiceshelf  | invoiceshelf | Not applicable  |
| **DB_NAME** | invoiceshelf  | invoiceshelf | /home/invoiceshelf/database/database.sqlite  |
| **DB_HOST** | db-mysql  |  db-pgsql | Not applicable  |
| **DB_PORT** | 3036  | 5432  | Not applicable  |

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










