# EU OSS Catalogue

## Description

This is the European Union OpenSource Software Catalogue of business solutions.
Mainly, the module federates data from national OSS catalogues but also from
any other arbitrary source and allows exposing such data via Drupal API, using
Drupal Views, controllers or, even, JSON:API/GraphQL endpoints.

It started as a Proof-Of-Concept based on the [publiccode.yml](https://yml.publiccode.tools/)
schema specifications but lately evolved as a full pluggable system that accepts
any kind of federation source, called _solution provider_ in the realm of this
module.

## Install & configure

TBD...

## Usage

TBD...

## Architecture

The architecture relies on Drupal plugin system. Let's see what plugin types
this module exposes, what is their role and how they are coming together to
provide the module's functionality.

### Plugin types

#### The Provider

Each provider plugin is responsible in fetching data from certain source called
_provider_. We've started from national OSS catalogues, such as [Developers
Italia](https://developers.italia.it), but this plugin type allows to fetch data
from any source by defining its own logic. The only constraint is that data
fetched by the plugin should be an array of array items. Each item is a
structured array, following the same schema. The plugin is responsible also for
the consistency validity of the data that is fetched.

Each provider plugin must designate a _parser_ plugin in their annotation block.

#### The Parser

The parser plugin has deep knowledge about data received from the _provider_. As
each _provider_ should designate a _parser_, the later knows how to process data
produced by the _provider_. Then, why we need a _parser_ when we already have a
_provider_? Well, several providers might use the same data structure, thus the
same parser. A good example are the [Developers Italia](
https://developers.italia.it) and ****GER**** catalogues as they share the same
[publiccode.yml](https://yml.publiccode.tools/) schema for describing OpenSource
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

### The process

In order to manage the performance aspects the fetching process is following
this path:

#### Fetch from provider

This phase is triggered by the `eu_oss_catalogue` service by the `::fetch()`
method. The service calls the _provider_ plugin which created an indexed array
of solutions. Each solution is a structured array following the same schema. The
_provider_ plugin is also responsible to provide valid data by performing schema
validation checks.

OSS Solutions are stored using the `EuOssCatalogueFetchedSolution` data model,
which is a value class object. Third-parties are able to subscribe to the
`EuOssPostFetchEvent` and alter the data stored in
`EuOssCatalogueFetchedSolution` value object.

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
the provider is the sole provider in `oss_source` field, it deletes the node.
Otherwise, it removes that provider form `oss_source`). The assumption should be
verified again.

### Triggering the process

The module ships with a Drush command that allows to fetch and queue solutions
from the command line.

Example:
```bash
./vendor/bin/drush eu-oss:fetch developers_italia
```

This command queries the Developers Italia provider endpoint, fetches the data
and populates the queue. Later, the cronjob is triggering the queue worker which
is gradually creating or updating the OSS Solutions.
