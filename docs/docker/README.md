# Install Joinup with Docker

## Requirements

* Docker
* docker-compose

## Getting started

The setup takes place in 3 steps.
1. Copy the files needed for the installation to work. This include building up
your `.env` and `docker-compose.override.yml` files.
2. Setup your prefered settings. The docker is supposed to work out of the box,
but for development purposes, you might want to change some settings. The `.env`
includes settings for downloading/restoring the databases, running tests etc.
The `docker-compose.override.yml` includes settings for the ports, volumes etc.
3. Start the containers and run the appropriate commands from below depending on
the way you want to run Joinup.

### 1.1 Environment variables

Copy the `.env.example` file over to `.env` file in the root directory where
you should assign values for environment variables containing sensitive data,
such as passwords, tokens, hashes or even custom preferences. These variables
are not committed in VCS and due to their nature should be manually set.

More information about these environment variables can be found in the
`.env.dist` file.

### 1.2 Set up the docker-compose.override.yml file

Copy the `docker-compose.override.yml.dist` file over to
`docker-compose.override.yml` file in the root directory. This file is not
committed in VCS and due to its nature should be manually set. Configuration
from `docker-compose.yml` can be overwritten in `docker-compose.override.yml`
file. Copy `docker-compose.override.yml.dist` over to
`docker-compose.override.yml`. Port mappings are not declared in the main
`docker-compose.yml` file because this is developer custom setting, so you'll
need to set up yours in the override file.

**macOS users**: As Docker has performance issues when the host machine is a
_macOS_ system, the setup needs more attention. Please read carefully all the
suggestions documented in the `docker-compose.override.yml.dist` file, specific
to _macOS_ users and fix the lines in `docker-compose.override.yml` file.
@todo This is probably not needed anymore.
@see https://github.com/docker/for-mac/issues/5402 for gRPC-FUSE performance.

### 1.3 Set up your local runner.yml.

Copy the `runner.yml.dist` file over to `runner.yml` file in the root directory.
The file already contains 2 shortened commands for installing the codebase and
installing a clean website. You can add more commands in this file. The
`runner.yml` file is not committed in VCS and due to its nature should be
manually set.

See below section `Using the runner` for more information.

### 2.1 Personal directories

In order to persist the data between container restarts, the `volumes` entry in
the `docker-compose.override.yml` file is used. This entry is commented out by
default.

The following directories are needed to properly develop on docker:
* The `~/.ssh` directory is used to store the SSH keys for GitHub
authentication. If you want to manage the repositories with SSH, you need to
add your SSH keys in this directory.
* The `~/.composer` directory is the global configuration directory for
Composer. It is used to store the Composer cache and the authentication tokens.
* The `~/.npm` directory is used to store the NPM cache.

### 2.2 Hosts file

In order to access the website, you need to add the following line to your
`hosts` file:

```bash
127.0.0.1 web
```
This will allow you to access the website at http://web:8080/web.

### 3.1 Starting the containers

To start the containers, you can execute the following command from the project
root directory:

```bash
$ docker-compose up --build --force-recreate -d
```

This will automatically read the `docker-compose.yml` file as a source. The `-d`
options will start the containers in the background. If you omit the `-d`
option, `docker-compose` will run in the foreground.

### 3.2 Stopping the containers

To stop the containers, you can use the command `docker-compose down` from the
same directory as the `docker-compose.yml`. Using this command, however, will
only stop the machine and will not destroy the volume that was created with it.
To clean the volume as well, use the `-v` parameter as `docker-compose down -v`.

### 3.3 Handling XDEBUG

@todo Verify the bug that prevents XDEBUG from working properly when the
containers are started with the `DISABLE_XDEBUG` environment variable set to
`True`.

During development XDEBUG can be enabled or disabled by running the following:

```bash
# Enable
$ docker-compose exec web phpenmod xdebug && docker-compose restart web
# Disable
$ docker-compose exec web phpdismod xdebug && docker-compose restart web
```
The restart is needed in order to apply the settings in the PHP-fpm pool.

### 3.4 Installing the codebase

Run the following command to install all packages in the vendor folder:

```bash
$ docker-compose exec --user www-data web composer install
```

### 3.5 Reset the environment

In order to restart the containers and reset the environment, you can run the
following command from the project root directory:

```bash
$ docker-compose down -v && docker-compose up -d --build --force-recreate
```

This will stop the containers, remove the volumes and start the containers
again.

### 3.6 Install an empty website

From the project root, run:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/run toolkit:install-clean
```

This will install a clean website with the default configuration. The website is
available at http://web:8080/web.

### 3.7 Install a cloned site

A _cloned site_ install is also restoring the databases from the production and
is running all update scripts.

#### 3.7.1 Downloading the databases

Before proceeding, make sure you set manually the `ASDA_*` environment variables
in `.env` file. The values should be provided by the team.

Then, run the following command to download the databases:
```bash
$ docker-compose exec --user www-data web ./vendor/bin/run dev:download-databases
```
Please, note that this requires the containers to be fired up. The databases
will be stored in the `./db/` directory.

#### 3.7.2 Restoring the databases

For docker, the databases are restored on container start. To do so, you need to
set the following environment variable in the `.env` file:
```
DOCKER_RESTORE_PRODUCTION=yes
```
After that, you will need to reset/restart the environment, as described in the
previous section.

**Important**: The SQL database is restored from a dump file, which is not that
fast. The database container will take some time to complete the restoration and
you can monitor this process by running the following command:
```bash
$ docker-compose logs -f mysql
```
Please, wait until the container reports that it is ready for connections before
proceeding to the deploy commands.

#### 3.7.3 Install the cloned site

After containers are up and got the chance to import from dump/snapshots, run
this sequence of commands:

```bash
# Sets up the settings for deployment.
$ docker-compose exec --user www-data web ./vendor/bin/run drupal:settings install

# Runs the deployment.
$ docker-compose exec --user www-data web ./vendor/bin/run toolkit:run-deploy
```

#### 3.7.4 Install additional modules and users

Further modules are available to help with development. To install them, run:

```bash
# Disable the config-readonly.
$ docker-compose exec --user www-data web ./vendor/bin/run config-readonly:disable

# Install the demo users.
$ docker-compose exec --user www-data web ./vendor/bin/run dev:demo-users

# Install the additional modules.
$ docker-compose exec --user www-data web ./vendor/bin/run dev:install-modules
```

A list of demo users will be installed in the website. The users are:
* "User": An authenticated user with email `user@example.com` and password
`user`.
* "Admin": An administrator user with email `admin@example.com` and password
`admin`.
* "Moderator": A moderator user with email `moderator@example.com` and password
`moderator`.
The `dev:demo-users` will also setup the CAS mock server which will allow you to
login bypassing EU Login.

The demo modules include views_ui, field_ui, and other helpful modules.

## Using the Task Runner

A Task Runner is available to help with the development. It is based on the
[Robo](https://robo.li/) library. The Task Runner is available in the container
`web` and can be executed with the following command:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/run
```
You can generate your own commands by adding them to the `runner.yml` file in
the root directory. You can override and create your own commands there.

For example, the whole deployment command can be executed with the following:

```yaml
commands:
  local-deploy:
    # Overrides the toolkit:install-clone command but does not install the
    # databases as they are restored in the container startup.
    - task: run
      command: joinup:environment
    - task: run
      command: redis:flush-all
    - task: run
      command: drupal:settings
      arguments:
        - install
    - task: run
      command: toolkit:run-deploy
    - task: run
      command: config-readonly:disable
    - task: run
      command: dev:demo-users
    - task: run
      command: dev:install-modules
    - task: run
      command: config-readonly:disable
    - task: exec
      command: ${drush.bin}
      arguments:
        - cas-mock-server:start
      options:
        yes: null
        root: ${joinup.site_dir}
    - task: exec
      command: ${drush.bin}
      options:
        yes: null
      arguments:
        - pm:uninstall
        - joinup_eulogin
    - task: exec
      command: ${drush.bin}
      arguments:
        - pm:enable
        - filecache
    - task: run
      command: drupal:settings
      arguments:
        - site-clone
```
The above command also includes some additional commands that are not part of
the deployment process, but are useful for development.

This is the recommended script for local development. You can extend the command
at will but the above is a good mandatory starting point.

After the MySQL container is ready for connections, you can run the following
command to install the site:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/run local-deploy
```
Another useful override is also for the clean install process.

```yaml
  local-install:
    - task: run
      command: toolkit:install-clean
    - task: run
      command: dev:demo-users
    - task: run
      command: dev:install-modules
```

The project offers a `runner.yml.dist` file that can be used as a template and
contains these two commands as a starting point. Copy the file to `runner.yml`
and you will be able to use the commands.

After having the `runner.yml` you will only need the commands `local-install`
and `local-install` to install the site though it is recommended to know what
they really do.

## Accessing the containers

All containers are accessible through the command

```bash
$ docker-compose exec my_container_name "/bin/bash"
```

Depending on the container (and its base image), it might be that `/bin/bash` is
not available. In that case, `/bin/sh` and `sh` are good substitutes.

Depending on the user you want to use, you might need to add `--user {username}`
option before the container name, for instance to access the `web` container as
user `www-data`, add `--user www-data`.

## Setup your test files

Run the following command to setup the test files:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/run toolkit:build-dev
```
Your `behat.yml` file should now be in the root folder as well as your
`phpcs.xml`.

## Running the tests

### Behat tests

In order to run a behat test, you have to distinguish between the two types of
behat tests we have. The ones running in the clean environment and the ones
running in the cloned environment.
Almost all tests are meant to run in a clean environment. You can run them in
the cloned environment, but results are not guaranteed and also basing your
results on a cloned environment is not guaranting that the tests will pass in
the clean environment.

#### Running the tests in the clean environment

The clean environment is the one that is installed by the
`toolkit:install-clean` or the `local-install` command. In order to run the
tests in this environment, you have to run the following command:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/behat --profile=clean --config=behat.yml /tests/features/path/to/feature.feature
```

#### Running the tests in the cloned environment

The difference in the command is that you should not include the
`--profile=clean` option. The command should look like this:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/behat --config=behat.yml /tests/features/path/to/feature.feature
```

### PHPUnit tests

The PHPUnit tests are run in the same environment as the Behat tests. The
command is the following:

```bash
$ docker-compose exec --user www-data web ./vendor/bin/phpunit path/to/your/test/Class.php
```
The `phpunit.xml` is consumed automatically.

## Next steps

Please, check the [PhpStorm configuration](./phpstorm_setup.md) for
more information on how to setup your IDE to work with the project. Currently,
the setup is only available for PhpStorm.
