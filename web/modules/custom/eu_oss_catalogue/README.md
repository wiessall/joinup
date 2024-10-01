# EU Open Source Solutions Catalogue | EU OSS Catalogue
### (ongoing MVP version)

This is a Drupal Module implementing a first experimental MVP version of an EU
Open Source Solutions Catalogue (EU OSS Catalogue),
in the context of interest for National & Local Public Administrations.

## EU Initiative Context

The significance of Open Source software in public sector across Europe has been
affirmed by governments increasingly incorporating Open Source software as part
of their country’s ICT and political and legal frameworks.

The [European Commission Open Source Strategy 2020-2023](https://commission.europa.eu/about-european-commission/departments-and-executive-agencies/informatics/open-source-software-strategy_en)
aims to encourage sharing and reuse of software and applications as well as
data, information, and knowledge as progress towards digital autonomy of
Europe’s own independent digital approach is a priority objective of the
strategy.

The [2020 Berlin Declaration](https://digital-strategy.ec.europa.eu/en/news/berlin-declaration-digital-society-and-value-based-digital-government)
calls for European institutions to promote “the development, sharing and re-use
of Open Source standards, solutions and specifications across borders.”

For a Public Administration to reuse another administration’s IT solution, it
should first know that the solution exists, where it is located, and then trust
that it is safe, technically mature, and documented enough to be reused. There
are several existing national initiatives which aim to aggregate Open Source
solutions for public use such via catalogues, repositories or registries.
However, they differ in scope and maturity – some have a national focus and
some of them are developer-oriented with complex APIs to contribute data,
some include software code and other publish the code on external common code
repositories such as GitHub or GitLab (managed by private companies).
Also, different metadata standards are used to describe the solutions
aggregated in these catalogues. They often lack comprehensive use cases and a
clear categorisation that can help both technical and non-technical users find
Open Source solutions that meet their needs.

Across the EU, Member States have put in place OSS initiatives which aim to
aggregate Open Source solutions for public use such via catalogues, repositories
or registries. However, they differ in scope and maturity, so as in metadata
standards.

This hinders cross-border reuse of Open Source solutions in the public sector,
and there is a clear opportunity for a European-level action (EU OSS Catalogue)
that could anchor/federate with the ecosystem of existing catalogues, and
potentially facilitate their interoperability.

The purpose of the EU Applications Catalogue is to act as a central directory
(or else ‘index’) at European Union level, where one can share, search, discover
and collaborate on Open Source solution software in use at European Public
Services. These applications would have either been built by Public
Administrations themselves, or by Open Source communities/ vendors and in use at
the public sector.

The long-term vision for the EU OSS Catalogue is to manage and protect Open
Source solutions at EU level, treat them as a collective, shared and valuable
European asset, promote collaboration across Open Source communities within
Public Administrations, contributing to the common good of Public Services.

## Architectural and Functional Overview

In this implementation, the module approach involves federation
(fetching and mirroring) of data from national/local OSS catalogs (based on Git
repositories/sources) equipped with APIs (REST API or GraphQL), as well as from
any other single GIT source compliant with metadata standards and quality
criteria actually set for the EU OSS catalogue.

This ongoing MVP version is initially focusing on OSS Solutions sources
(Git Repositories) of interest of Public Administrations (produced by or for),
adopting the [Publiccode.yml Metadata Standard](https://github.com/publiccodeyml/publiccode.yml)
and its [publiccode.yml data model / schema](https://yml.publiccode.tools/)
throughout an internal MIDM (Minimum Interoperability Data Model) able to map
and semantically align with it.

Both its Data Model and internal architecture are however designed to be scalable and
based on a fully pluggable system that could potentially evolve and map to any
type of federated source.


## Install & configure

TBD...

## Usage

TBD...

## Internal Architecture

The architecture relies on Drupal plugin system. Let's see what plugin types
this module exposes, what is their role and how they are coming together to
provide the module's functionality.

### Plugin types

#### The Provider

Each provider plugin is responsible in fetching data from certain source called
_provider_.

At the moment the following 2 provider plugins are implemented (as wip):

- Developers Italia: Italian National OSS Catalogue,
by https://developers.italia.it website
- Open CoDE: German National OSS Catalogue,
by https://opencode.de website. (GitLab (self hosted))

These plugin type allows to fetch data from any source by defining its own
logic. The only constraint is that data fetched by the plugin should be an
array of array items. Each item is a structured array, following the same
schema. The plugin is responsible also for the consistency validity of the data
that is fetched.

Each provider plugin must designate a _parser_ plugin in their annotation block.

#### The Parser

The parser plugin has deep knowledge about data received from the _provider_. As
each _provider_ should designate a _parser_, the later knows how to process data
produced by the _provider_. Then, why we need a _parser_ when we already have a
_provider_? Well, several providers might use the same data structure, thus the
same parser. A good example are the [Developers Italia](
https://developers.italia.it) and [Open CoDE](https://opencode.de) catalogues as they share the same
[publiccode.yml](https://yml.publiccode.tools/) schema for describing Open Source
Software Solutions.

The main role of a parser plugin is to build the OSS Solution node entity, ready
to be saved. However, the parser doesn't actually save the node as the provider
and 3rd-parties still can apply their adjustments.

The parser is also responsible for schema validation. Parser plugins are
instantiated early, just after data has been fetched and called to do the schema
validation.

#### The Resolver

The resolver plugin is coupled with a specific node field through the field
config third-party-settings. A resolver knows how to pick the value from the
fetched data corresponding to certain field and how to apply transformations in
order to make the value compatible with the Drupal entity field expected data.

The most basic resolver is the `default` plugin that passes the value from
fetched data as it is to the Drupal node field.

A node field might define several resolvers depending on the _parser_ plugin
that is calling it.

#### The Code Hosting Platform

These are plugins in charge with making API calls against a code hosting
platform, such as GitHub, GitLab, BitBucket, etc.

##### GitHub

GitHub API calls are authenticated. You should obtain an access token and store
it in the `settings.php` file:

```php
$settings['eu_oss_catalogue']['platform_github']['access_token'] = '...';
```

##### GitLab

GitLab API calls are not authenticated, thus there's no need for any
configuration. However, some GitLab instances, including gitlab.com, are
configured with API calls rate limits. For instance, gitlab.com rate limits are
listed [here](https://docs.gitlab.com/ee/user/gitlab_com/#gitlabcom-specific-rate-limits).
For self-hosted GitLab instances that doesn't publish their rate limits, you can
find them by inspecting some specific headers, see
[rate limits response headers](https://docs.gitlab.com/ee/administration/settings/user_and_ip_rate_limits.html#response-headers)
for details.

### The process

In order to manage the performance aspects the fetching process is following
this path:

#### Fetch from provider

This phase is triggered by the `eu_oss_catalogue` service by the `::fetch()`
method. The service calls the _provider_ plugin which created an indexed array
of solutions. Each solution is a structured array following the same schema. The
_provider_ plugin is also responsible to provide valid data by performing schema
validation checks.

OSS Solutions are stored using the `FetchedSolution` data model, which is a
value class object. Third-parties are able to subscribe to the
`EuOssPostFetchEvent` and alter the data stored in `FetchedSolution` value
object.

#### Queue fetched solutions

The list retrieved in the previous step is queued using Drupal Queue API, to
be processed later, on cron run.

#### Consuming the queue

A queue worker plugin consumes the queue by calling using the `eu_oss_catalogue`
service `::createOrUpdateSolution()` method. The method, actually, is calling
the _parser_ plugin which is responsible to build the OSS Solution node entity.
The parser is doing this with help from the _resolver_ plugins for each field.

If an OSS Solution with the same URL exists, it will be updated with the fetched
values. Otherwise, a new OSS Solution node entity will be created.

After the parser job is done, there are still other parties that are invited to
do their alteration against the OSS Solution node entity. For instance, the
_provider_ plugin might apply some very specific customizations to the resulting
node. After that, any 3rd-party may listen to the `EuOssSolutionPreSaveEvent`
event and apply their changes to the OSS Solution node entity.

Finally, `eu_oss_catalogue::createOrUpdateSolution()` is saving the node in the
Drupal node entity storage.

#### Removing dead OSS Solutions

TBD (based on discussion from DDD2023 Vienna, we queue an item with the list of
fetched solutions and their provider. Then the worker looks at each solution. If
the provider is the sole provider in `oss_source` field, it archives the node.
Otherwise, it removes that provider form `oss_source`). The assumption should be
verified again.

### Triggering the process

The module ships with a Drush command that allows to fetch and queue solutions
from the command line.

Example:

Run the following, to fetch from
[Developers Italia (Italian National OSS Catalogue)](https://developers.italia.it)
```bash
./vendor/bin/drush eu-oss:fetch developers_italia
```

Run the following, to fetch from
[Open CoDE (German National OSS Catalogue)](https://opencode.de)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:open_code
```

Run the following, to fetch from
[Code Europa EU (European Commission OSS Catalogue)](https://code.europa.eu)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:code_europa_eu
```

Run the following, to fetch from
Fetch solutions from Dutch catalogue
```bash
./vendor/bin/drush eu-oss:fetch dutch_catalogue

This command queries the Developers Italia provider endpoint, fetches the data
and populates the queue. Later, the cronjob is triggering the queue worker which
is gradually creating or updating the OSS Solutions.

You can trigger manually OSS catalogue queue worker:
```bash
./vendor/bin/drush queue:run eu_oss_catalogue
```
