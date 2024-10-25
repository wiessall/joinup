# Joinup website

This is the source code for https://interoperable-europe.ec.europa.eu.

Joinup is a collaborative platform created by the European Commission and funded
by the European Union via the [Interoperable Europe](https://interoperable-europe.ec.europa.eu/interoperable-europe)
Programme.

It offers several services that aim to help e-Government professionals share
their experience with each other. We also hope to support them to find, choose,
re-use, develop and implement interoperability solutions.

The Joinup platform is developed as a [Drupal](https://www.drupal.org)
web application, and therefore tries to follow the "open-source way" as much as
possible.

You are free to fork this project to host your own collaborative platform.
However, Joinup is not a full reusable solution because it doesn't guarantee
backwards compatibility, nor a full upgrade path between two versions.

If you download and install your own instance, you're responsible for update and
adapt the code when new Joinup versions are available.

Joinup is licensed under the [EUPL](https://interoperable-europe.ec.europa.eu/community/eupl/og_page/eupl),
which is compatible with the GPL.

## Development

We're using [DDEV](https://ddev.com), a Docker-based PHP development tool, for a
streamlined and unified development process.

### Before you begin

* Make sure you use a good PHP [IDE](https://en.wikipedia.org/wiki/Integrated_development_environment)
  such as [PhpStorm](https://www.jetbrains.com/phpstorm/), [VS Code](https://code.visualstudio.com)
  or [Eclipse](https://eclipseide.org). Joinup code is complex and needs good
  tools.
* Get familiarised with [DDEV](https://ddev.com). Check the DDEV [documentation](https://ddev.readthedocs.io).

### Install DDEV

* Install a Docker provider by following DDEV [Docker Installation](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/)
  instructions for your Operating System.
* [Install DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/),
  use the documentation that best fits your OS.
* DDEV is used mostly via CLI commands. [Configure shell completion & autocomplete](https://ddev.readthedocs.io/en/stable/users/install/shell-completion/)
  according to your environment.
* Configure your IDE to take advantage of the DDEV features. This is a critical
  step to be able to test and debug your Joinup. Remember, the website
  runs inside Docker, so pay attention to these configurations:
  - [PhpStorm Setup](https://ddev.readthedocs.io/en/stable/users/install/phpstorm/)
  - [Configure](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/step-debugging/)
    PhpStorm and VS Code for step debugging.
  - Profiling with [xhprof](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/xhprof-profiling/),
    [Xdebug](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/xdebug-profiling/)
    and [Blackfire](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/blackfire-profiling/).

### Deploy the codebase

#### Checkout the project

```shell
git clone git@git.fpfis.tech.ec.europa.eu:ec-europa/digit-joinup-reference.git joinup
cd joinup
```

### Start DDEV

Inside the cloned project run:

```shell
ddev start
```

This command will fire up the Docker containers and add all configurations.

### Install dependencies

```shell
ddev composer install
```

This will install the PHP and Node dependencies. The codebase is deployed.

### Configure sensitive data (secrets)

* Copy the `.env.example` file as `.env`. The `.env` file is not under VCS
  control and is used to override values stored into `.env.dist` file but, more
  important, to store here sensitive data, such as credentials, tokens,
  passwords, etc.
* You should fill the `.env` file environment variables with values. Each
  variable is documented in `.env.example`.

### Install Joinup

There are two ways to install Joinup:

### Empty site

This way of installing is referred also as a _clean installing_. The site
exposes all functionalities but doesn't come with content and users (except the
superuser).

Most of the tests are run against the empty site, so this is helpful when you
write tests or run most of the tests.

#### Install an empty site

```shell
ddev install
```

#### Add demo users (Optional)

As the site doesn't have users, you might want to add some demo users:

```shell
ddev run dev:demo-users
```

### Site rebuilt from production

This installation type, referred also as a _clone installing_, rebuilds the site
by restoring data from production. Note that sensitive data, such as user's full
names, emails, etc. is already sanitized.

#### Nextcloud credentials

Make sure that you followed the [Configure sensitive data (secrets)](#configure-sensitive-data-secrets)
section, and you set the `NEXTCLOUD_*` environment variables in `.env` file. The
values should be provided according to these [instructions](https://webgate.ec.europa.eu/fpfis/qa/how-to?nid=22351).
These credentials are needed to be able to download the Joinup databases dumps
and snapshots.

#### Download database dump/snapshots

Joinup needs to restore dump/snapshots from three sources: MySQL, Virtuoso and
Solr; to be able to rebuild the website with production data:

```shell
ddev download-databases
```

#### Actually rebuild the site

```shell
ddev rebuild
```

This command:

* Restore the dump and snapshots,
* Runs the update, post-update functions,
* Synchronizes configuration from `config/sync` directory,
* Runs the `hook_deploy()` functions.
* Performs other needed tasks.

There are cases when you only want to restore the database dump and snapshots,
for instance, to inspect existing values from the backends:

```shell
ddev restore-databases
```

### Launching

Launch the site as anonymous:

```shell
ddev launch
```

Launch the site as superuser:

```shell
ddev drush uli
```

The command will provide a URL that allows you to log in as superuser.

### Running commands

#### Any command in any container

Run a command in the default service container (`web`):

```shell
ddev exec <command>
```

Run a command in any other service container (including `web`):

```shell
ddev exec -s <service> <command>
```

Alternatively, you can SSH into any container that has some advantages over
`ddev exec`, such as keeping the command history.

SSH into main (`web`) container:

```shell
ddev ssh
```

SSH into any container:

```shell
ddev ssh -s <service>
```

#### Task runners

Joinup is using two task runners:

* [Drush](https://github.com/drush-ops/drush): This is the official Drupal shell tool.
* [Toolkit](https://github.com/ec-europa/toolkit): The European Commission PHP runner.

##### Drush

Launch a Drush command:

```shell
ddev drush <command>
```

##### Toolkit

```shell
ddev run <command>
```

Note that Toolkit uses its own configuration. Few aspects:

* Apart from configuration provided by Toolkit, Joinup exposes its own
  configuration under `resources/runner`.
* You can override any configuration by creating a `runner.yml` file in the
  project's root directory. Note that this file is not under VCS control.
* You can inspect the configuration. Find out how by running this command:
  ```yaml
  ddev run config --help
  ```

#### Developer local commands

Developers love to create local shorthand commands to run a more sophisticated
command or a sequence of commands that they run often but still don't qualify to
merged into the project `.ddev/commands` because they are not generic enough.

As a naming convention, such commands can be created by prefixing the command
file name with underscore (`_`). For instance, the following command, having
this path: `.ddev/commands/virtuoso/_say_hello` will run inside the `virtuoso`
service container but will not be committed in Git:

```shell
#!/bin/bash
## Description: Say hello
## Usage: say-hello
echo "Hello!"
```

Find out more on how to write custom commands [here](https://ddev.readthedocs.io/en/stable/users/extend/custom-commands/)

### Locally exposed services

* https://joinup.ddev.site, http://joinup.ddev.site — The website
* http://joinup.ddev.site:8890/sparql — The SPARQL endpoint
* http://joinup.ddev.site:8983 — The Solr admin UI
* https://joinup.ddev.site:7900, http://joinup.ddev.site:7910 — The Selenium
  tests viewer
* https://joinup.ddev.site:8026, http://joinup.ddev.site:8025 — The mailcatcher
  UI (Mailpit)
* To connect locally to MySQL, you'll need to find the port as this is
  dynamically allocated each time. Run:
  ```shell
  ddev describe -j | jq -r .raw.dbinfo.published_port`
  ```
  and connect from host to `mysql://db:db/127.0.0.1:<port>/db`.

### Testing & code quality checks

Testing frameworks used by Joinup:

#### Behat testing

* See https://behat.org
* Run Behat tests from IDE or from CLI:
  ```shell
  ddev exec vendor/bin/behat [arguments and options]
  ```

###### Update / post-update / deploy hooks

Tests covering such hooks should be placed under `tests/features/update` and
features and/or scenarios should be tagged with `@group-clone` and
`@update:[function]`, where `[function]` is the update hook/function name.
See `\Drupal\joinup\Context\UpdateContext`.

###### Unused step definitions

In order to prevent stale code in tests, the CI runs the following command to
detect unused Behat step definitions, and will fail if any are found:
```shell
  ddev run testing:unused-behat
```
See the `unused` Behat profile in [behat.yml.dist](behat.yml.dist).
Relies on [nicwortel/behat-unused-step-definitions-extension](https://github.com/nicwortel/behat-unused-step-definitions-extension)

#### PHPUnit testing

* See https://phpunit.de
* Run PHPUnit tests from IDE ot from CLI:
  ```shell
  ddev exec vendor/bin/phpunit [arguments and options]
  ```
* PHPUnit testing includes Drupal-style tests such as kernel, functional and [existing
  site](https://gitlab.com/weitzman/drupal-test-traits) but also plain PHPUnit
  tests.

#### Static code analysis

Joinup uses GrumPHP, which runs all tasks defined in [grumphp.yml.dist](grumphp.yml.dist).
See [GrumPHP documentation](https://github.com/phpro/grumphp/blob/v2.x/doc/commands.md#run).

* Run all tasks:
```shell
ddev grumphp run
```
* Alternatively, run 1 or more tasks:
```shell
ddev grumphp run --tasks=phpcs,phpstan,phplint,yamllint,eslint,stylelint
```

GrumPHP gets triggered on git pre-push hook by default. To change this behavior,
specify in your runner.yml:
```yaml
dev:
  # Options: pre-push / pre-commit / disable
  git_hook_on: pre-push
```

#### Watcher for compiling themes assets

* Run task:
  ```shell
  ddev npm run watch
  ```
* PhpStorm and VS Code, see our [NPM watch developer documentation](docs/npm_watch.md).

### Upgrade process

Joinup offers only _contiguous upgrades_. For details and naming conventions,
please read documentation of [ValidUpdateNameSniff](src/CodingStandards/Sniffs/NamingConventions/ValidUpdateNameSniff.php)
coding standards rule.

### Technical details

For some additional details in our SPARQL related functionality, see our
[SPARQL developer documentation](docs/sparql.md).

### Mailpit

Mailpit is a tool that allows you to catch all emails sent by the application
during development. Mailpit is optional, as the default setup of Joinup, is to
use the devel mail server which stores the emails as text messages in the tmp
directory. However, Mailpit is more convenient to use, as it provides a web UI
to view the emails.
You can access the Mailpit UI at https://joinup.ddev.site:8026.

### Additional information

* [Code of conduct](docs/code_of_conduct.md)
* [Deployment workflow](docs/release_workflow.md)
* [Contributors guide](docs/contributing.md).
