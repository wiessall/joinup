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

### GitHub Access Token

For fetching data from GitHub repositories, configure an access token in your `settings.php` file:

```php
$settings['eu_oss_catalogue']['platform_github']['access_token'] = 'your_github_token_here';
```

### Hosting Platform Configuration

Configure custom hosting platforms (GitLab instances, GitHub Enterprise, etc.) through the Drupal admin interface:

`/admin/config/eu-oss-catalogue/hosting-platform`

### Cron Configuration

The module uses Drupal's cron system to process the queue of fetched solutions. Ensure cron is running regularly:

```bash
./vendor/bin/drush cron
```

## Usage

The module provides Drush commands to fetch OSS solutions from various national catalogues and hosting platforms. Fetched data is queued and processed via cron, creating or updating OSS Solution nodes.

See the "Triggering the process" section below for detailed command examples.

## Internal Architecture

The architecture relies on Drupal plugin system. Let's see what plugin types
this module exposes, what is their role and how they are coming together to
provide the module's functionality.

### Plugin types

#### The Provider

Each provider plugin is responsible for fetching data from a specific source called
a _provider_.

The module currently supports the following providers:

**National Catalogues:**
- **Open Code Belgium** (BE) - Belgian OSS Catalogue
- **Developers Italia** (IT) - Italian National OSS Catalogue
- **Offentligkod.se** (SE) - Swedish National Catalogue
- **Awesome CodeGouvFr** (FR) - French National Catalogue
- **Developer Overheid** (NL) - Dutch National OSS Catalogue
- **Avoinkoodi.fi** (FI) - Finnish OSS Catalogue
- **Department of Information Technology Services** (CY) - Cyprus national department promoting open-source solutions for public sector IT
- **OGCIO Building Blocks** (IE) - Reusable open source digital components for Irish public services, delivered under the Office of the Government Chief Information Officer

- Developers Italia: Italian National OSS Catalogue,
by https://developers.italia.it website
- Open Code: German National OSS Catalogue,
by https://opencode.de website. (GitLab (self hosted))

**Other:**
- **Custom** - Solutions not part of any national catalogue

These plugin types allow fetching data from any source by defining custom logic.
The only constraint is that data fetched by the plugin must be an array of items,
where each item is a structured array following the same schema. The plugin is
responsible for data consistency and validity.

Each provider plugin must designate a _parser_ plugin in their attribute definition.

#### The Parser

The parser plugin has deep knowledge about data received from the _provider_. As
each _provider_ should designate a _parser_, the later knows how to process data
produced by the _provider_. Then, why we need a _parser_ when we already have a
_provider_? Well, several providers might use the same data structure, thus the
same parser. A good example are the [Developers Italia](
https://developers.italia.it) and [Open Code](https://opencode.de) catalogues as they share the same
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

GitHub API calls are authenticated. See the "Install & configure" section for
access token configuration.

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

#### Publiccode.yml validation

Before creating or updating an OSS Solution, the publiccode.yml content is
validated against the standard schema using `PublicCodeValidatorInterface`.

The validation uses the [publiccode-validator-api](https://github.com/italia/publiccode-validator-api)
service, which provides a REST API for publiccode.yml validation.

**For new solutions:**
- If publiccode.yml is invalid, the solution is **rejected** (not created)
- Validation errors are logged to `joinup_log`

**For existing solutions (updates):**
- If publiccode.yml becomes invalid, the solution data is **not updated**
- Only tracking fields (timestamps, source hash) are updated
- Validation errors are logged to `joinup_log`
- The existing solution remains accessible with its previous valid data

This ensures data quality and prevents invalid or corrupted metadata from
entering the catalogue.

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

After all solutions are fetched and queued, a cleanup operation is automatically
queued. The cleanup process identifies OSS Solutions that were previously imported
from a provider but are no longer present in the latest fetch.

For each identified solution, the queue worker performs the following:
- Removes the provider from the solution's `oss_source` field
- If the provider was the sole source (solution has no other providers), the
  solution is **archived** (marked with `oss_archived = 1`)
- If the solution has other providers remaining in `oss_source`, it remains
  active with only the removed provider excluded

This ensures that solutions removed from a national catalogue are properly handled
while preserving solutions that exist in multiple catalogues.

### Triggering the process

The module ships with a Drush command that allows to fetch and queue solutions
from the command line.

Example:

Run the following, to fetch from
[Open Code Belgium](https://www.imio.be) (Belgian OSS Catalogue)
```bash
./vendor/bin/drush eu-oss:fetch imio
```

Run the following, to fetch from
[Developers Italia (Italian National OSS Catalogue)](https://developers.italia.it)
```bash
./vendor/bin/drush eu-oss:fetch developers_italia
```

Run the following, to fetch from
[Open CoDE (German National OSS Catalogue)](https://opencode.de)
```bash
./vendor/bin/drush eu-oss:fetch offentligkod_se
```

Run the following, to fetch from
[Awesome CodeGouvFr](https://code.gouv.fr/sources/#/awesome)
```bash
./vendor/bin/drush eu-oss:fetch awesome_code_gouv_fr
```

Run the following, to fetch from
[Digitaal gedreven door Gent](https://district09.gent)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:city_of_ghent
```

Run the following, to fetch from
[Developer Overheid](https://developer.overheid.nl)
```bash
./vendor/bin/drush eu-oss:fetch developer_overheid
```

Run the following, to fetch from
[Avoinkoodi.fi](http://avoinkoodi.fi)
```bash
./vendor/bin/drush eu-oss:fetch avoinkoodi_fi
```

Run the following, to fetch from
[Code Europa EU (European Commission OSS Catalogue)](https://code.europa.eu)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:code_europa_eu
```

Run the following, to fetch from
[Open CoDE (German National OSS Catalogue)](https://opencode.de)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:open_code
```

Run the following, to fetch from
[Department of Information Technology Services (Cyprus)](https://www.gov.cy/dmrid-dits/)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:dmrid_dits
```

Run the following, to fetch from
[OGCIO Building Blocks (Ireland)](https://www.gov.ie/en/govie-team/campaigns/building-blocks/)
```bash
./vendor/bin/drush eu-oss:fetch hosting_platform:ogcio
```

Run the following, to fetch from Other (custom solutions not part of any catalogue):
```bash
./vendor/bin/drush eu-oss:fetch custom:other
```

This command queries the configured provider endpoints, fetches the data
and populates the queue. Later, the cronjob is triggering the queue worker which
is gradually creating or updating the OSS Solutions.

You can trigger manually OSS catalogue queue worker:
```bash
./vendor/bin/drush queue:run eu_oss_catalogue
```

### Validation Commands

The module provides commands to validate publiccode.yml files against the standard schema.

Validate the publiccode.yml of an OSS solution node:
```bash
./vendor/bin/drush eu-oss:validate-node <NODE_ID>
```

Validate publiccode.yml content from a URL:
```bash
./vendor/bin/drush eu-oss:validate-url <URL>
```

Example:
```bash
./vendor/bin/drush eu-oss:validate-url https://raw.githubusercontent.com/example/repo/main/publiccode.yml
```
