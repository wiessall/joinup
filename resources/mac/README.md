# Joinup on macOS without Docker

Here you will find the steps to run the Joinup project on your Mac without
Docker.

## Prerequisites

- Brew, Composer, Drush, Apache and PHP: [Install Apache & Multiple php
versions](https://getgrav.org/blog/macos-catalina-apache-multiple-php-versions)

- Percona & Apache Virtual Hosts & DnsMasq: [Install mysql & Apache Virtual Hosts
& Dnsmasq](https://getgrav.org/blog/macos-catalina-apache-mysql-vhost-apc)

  If you want to modify Percona's data-dir:
  - Edit the `datadir` option in
    `/opt/homebrew/opt/percona-server/homebrew.mxcl.percona-server.plist` and in
    `/opt/homebrew/opt/percona-server/homebrew.percona-server.service`
  - Inititalize the new data directory:
    `mysqld --initialize-insecure --datadir <yourcustom-data-dir>`

- Memcached: [Install with Brew](https://formulae.brew.sh/formula/memcached)

  To install memcached extension for the current PHP version execute
  `pecl install memcached`.

  To get `libmemcached` and `zlib` paths:
  `brew --prefix [libmemcached|zlib]` (installer will ask these paths).

- Virtuoso: Use [version 7.2.6](https://github.com/openlink/virtuoso-opensource/releases/tag/v7.2.6.1). The macOS binary works
  well both on Intel/Silicon + Ventura.

- [Wget](https://formulae.brew.sh/formula/wget)

- [GNU Patch](https://formulae.brew.sh/formula/gpatch)

## Installation

1. Uncomment these lines on http.conf file **/usr/local/etc/httpd/httpd.conf**

   ```
   LoadModule vhost_alias_module lib/httpd/modules/mod_vhost_alias.so
   Include /usr/local/etc/httpd/extra/httpd-vhosts.conf
   ```

2. Add virtual-host **/usr/local/etc/httpd/extra/http-vhosts.conf**

   ```
   <VirtualHost *:80>
     ServerName joinup.test
     # Replace with your project path.
     DocumentRoot "/Users/.../.../joinup-dev/web"
     # Replace with your project path.
     <Directory "/Users/.../.../joinup-dev/web">
       AllowOverride all
       Require all granted
     </Directory>
   </VirtualHost>
   ```

3. Add new host in **/private/etc/hosts**

   ```
   127.0.0.1   joinup.test
   ```

4. Restart Apache

   ```bash
   $ sudo apachectl -k restart
   ```

5. **Only if** you have XDebug installed check php.ini file for the config:

   ```
   [xdebug]
   ;zend_extension=“xdebug.so”
   xdebug.remote_enable=1
   xdebug.remote_autostart=0
   xdebug.max_nesting_level=256
   ;xdebug.collect_params=3
   ;xdebug.profiler_enabled=1
   ;xdebug.profiler_output_dir=/tmp/
   ;xdebug.profiler_enable_trigger=1
   ```

## Setting up the project

### Clone the repository

```bash
$ git clone https://github.com/ec-europa/joinup-dev.git
```

### Create a local task runner configuration file

In order to override any configuration of the task runner (`./vendor/bin/run`),
create a `runner.yml` file in the project's top directory. You can override
there any default runner configuration, or any other declared in
`./resources/runner` files or in `runner.yml.dist`. Note that the `runner.yml`
file is not under VCS control.

Override path to Virtuoso and ISQL binaries. Note: whitespaces and special chars
must be escaped!
```yaml
virtuoso:
  bin: /Applications/Virtuoso\ Open\ Source\ Edition\ v7.2.app/Contents/virtuoso-opensource/bin/virtuoso-t

isql:
  bin: /Applications/Virtuoso\ Open\ Source\ Edition\ v7.2.app/Contents/virtuoso-opensource/bin/isql
```

### Environment variables

Sensitive data will be stored in [environment variables](
https://en.wikipedia.org/wiki/Environment_variable). See `.env.dist` for
details.

Copy `.env.dist` to `.env` and add the necessary overrides. Example:
```
DRUPAL_BASE_URL=http://joinup.test

# Database connection.
DRUPAL_DATABASE_HOST=localhost
DRUPAL_DATABASE_PORT=3306
DRUPAL_DATABASE_USERNAME=devuser
DRUPAL_DATABASE_PASSWORD=devpassword
DRUPAL_DATABASE_NAME=joinup

# SPARQL connection.
DRUPAL_SPARQL_HOSTNAME=localhost

DRUPAL_MEMCACHE_HOSTS=localhost:11211

SEARCH_API_SERVER_SS_SOLR_HOST=localhost

SIMPLETEST_BASE_URL=http://joinup.test
SIMPLETEST_DB=mysql://devuser:devpassword@localhost:3306/joinup
SIMPLETEST_SPARQL_DB=sparql://localhost:8890?module=sparql_entity_storage
MINK_DRIVER_ARGS_WEBDRIVER="['chrome', null, 'http://localhost:4444/wd/hub']"

DTT_BASE_URL=http://joinup.test
DTT_API_URL=http://localhost:4444/wd/hub
DTT_MINK_DRIVER_ARGS="['chrome', null, 'http://localhost:4444/wd/hub']"

WEBDRIVER_URL=http://localhost:4444/wd/hub
```

**Important!** For the NEXTCLOUD settings please contact your local developer

## Build the codebase

```bash
$ composer install
```

### Install and/or relink

```bash
$ brew unlink unixodbc
$ brew install virtuoso
$ brew unlink virtuoso
$ brew link unixodbc
$ brew link --overwrite virtuoso
```

### Setup Virtuoso

```bash
$ ./vendor/bin/run virtuoso:setup
$ ./vendor/bin/run virtuoso:start
```

[Check Virtuoso](http://localhost:8890/sparql)


### Setup Solr and check if it's running

Ensure Java SDK is available and whether the right JAVA_HOME env var is set up
properly.

```bash
./vendor/bin/run solr:download-bin
./vendor/bin/run solr:config
```

(Latter command will also start the Solr server.)

[Check Solr](http://localhost:8983/solr/#/)


### Selenium server (for Javascript tests)

1. Assuming that Java is already available (because of Solr), download the most
   recent Selenium Server 4.5.x release at
   https://github.com/SeleniumHQ/selenium/releases/tag/selenium-4.5.0.
   (I tried 4.5.10 and 4.5.11 too, but they didn't work for me.)
2. Make sure your local chromedriver binary matches the actual Google Chrome
   version. If you don't have Google Chrome, install it too.
3. Start the Selenium server:
   ```
   java -Dwebdriver.chrome.driver=`which chromedriver` \
     -jar <path-to-selenium-server-4.5.*.jar> \
     standalone
   ```

The recommended Selenium setup ("using Docker") in
[the root README file](./README.md#initial-setup) only works on Linux. On other
systems like macOS or Windows, Docker uses a virtual network inside its virtual
machine, which makes the Selenium container unable to access the web server on
your local environment.

[Check Selenium](http://localhost:4444/ui#/sessions)


## Basic commands

### Run `toolkit:install-clean`

```bash
$ ./vendor/bin/run toolkit:install-clean
```

### Download production databases

```bash
$ ./vendor/bin/run dev:download-databases
```

### Rebuild environment

```bash
$ ./vendor/bin/run toolkit:install-clone
```

### Unblock the admin user

```bash
$ drush user:unblock
```

### Login with the admin user

```bash
$ drush uli
```

## Switching between branches

This is needed when you'll have to switch a branch and keep your content up to
date:

```bash
$ ./vendor/bin/composer install
$ ./vendor/bin/run toolkit:install-clone
```
