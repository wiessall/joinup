# Welcome to the PhpStorm Docker setup guide!

## Interpreter setup

After you launch your project and copy the necessary files as described in the
[Docker Readme](./README.md), you will need to configure the PHP interpreter.

Head to File > Settings > Languages & Frameworks > PHP and click on the `...`
button.
Note that some builds of PhpStorm have the `PHP` option directly in the
`Settings` menu on the top.

From there, click on the `+` button and select `From Docker, Vagrant, VM,
Remote...`. Name your server `Docker`.

You will be prompted to select a server configuration. Select the
`Docker compose` option. Select the `docker-compose.yml` as well as your
`docker-compose.override.yml` as your configuration files and select `web` as
your server. Set the `PHP executable` to `/usr/bin/php`.

Click `OK` and you should see the `PHP` interpreter configuration page and the
path mapping should be set up automatically.

Next, you want to select the PHP language level according to the production
environment. You can communicate with the team to find out which version of PHP
is used in production.

## Debug setup

Next, expand the PHP section and click `Debug`. The only thing you will need to
do here is to set the maximum number of simultaneous connections to `9`.

## Test frameworks setup

This is an optional step as it will allow you to run the behat and PHPUnit tests
from within PhpStorm.
Head to File > Settings > Languages & Frameworks > PHP > Test Frameworks and
click on the `+` button. Select `PhpUnit by Remote Interpreter` and select the
`PHP` interpreter you just created. The configuration should be detected
automatically. Set the path for the default configuration file to
`/var/www/html/phpunit.xml`.

Next, click on the `+` button and select `Behat by Remote Interpreter`. Select
the `PHP` interpreter you just created. The configuration should be detected
automatically. Set the path for the default configuration file to
`/var/www/html/behat.yml`.

`behat.yml` files can be generated automatically according to your configuration
as described in the "Setup your test files" section of the
[Docker Readme](./README.md).

## Frameworks setup

Head over to File > Settings > Languages & Frameworks > PHP > Frameworks and
expand the `Drupal` section. Check the `Enable Drupal integration`, select the
`web` folder as your Drupal root and make sure the `Set up PHP|Include paths`
is selected.
Set the Drupal version as the version of the Drupal core you are using. You can
find the version in the `composer.json` file.

## Configurations setup

From your main window, head to Run > Edit Configurations. On the bottom left,
click the `Edit configuration templates`.

There are 2 configuration templates to setup:
1. Behat. In behat, set your interpreter options to include `--profile=clean`.
This will allow all non-clone tests to run. Also, select `Docker` as the
interpreter.
2. Next, head to the `PHP Web Page`. Select `Docker` as the Server and set the
`Start URL` to `/web`. You will see that the full URL below will show
`http://web:8080/web`. This is the correct URL.

Click `OK` twice to close the windows.

## Run a behat test

Now that everything is setup, you can run a behat test. Head to a behat test,
e.g. `tests/features/dashboard.feature`. Right click on the test and select
`Run 'dashboard.feature'`. You should see the test run in the console.

Congratulations! You have successfully setup PhpStorm to run behat tests.

## Debug a behat test

To debug a behat test, you will need to setup a breakpoint in the test. Head to
the test and click on the line number where you want to set the breakpoint. A
red dot should appear on the left side of the line number. Right click on the
test and select `Debug 'dashboard.feature'` or whatever the name of the test
is. You should see the test run in the console and the breakpoint should be
hit.

** Warning **: PHPStorm currently has no way of running the tests as the
`${DAEMON_USER}` (we cannot set the `${DAEMON_USER}` as the docker user as
there are multiple scripts ran as root after the docker image is built). If the
first time you run a test though PHPStorm, it might cause permission issues
when running the tests through the command line. If this happens, you will need
to run
```bash
$ docker-compose exec web chown -R ${DAEMON_USER}:${DAEMON_USER} /tmp
```
to fix the permissions. Note, that in the above command, you will need to
change the variables with the actual name of the user and group.
