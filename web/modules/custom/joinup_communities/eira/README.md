# EIRA Community Module

This module provides configuration for the EIRA Community.

## Introduction

The EIRA community hosts 2 vocabularies with us. The vocabularies are:
* eGovERA
* EIRA
* Solution type

Mappings are already set in third party settings of the vocabulary and related
fields. The module also offers a normalizer subscriber to handle extra
properties that are needed during import in
[/web/modules/custom/joinup_communities/eira/src/EventSubscriber/NormalizeSubscriber.php](src/EventSubscriber/NormalizeSubscriber.php).

## eGovERA

The eGovERA vocabulary's file structure and properties are very similar to the
EIRA vocabulary. That allows us to use the same alterations for both
vocabularies.

The alterations are done in the
`\Drupal\joinup_taxonomy\EventSubscriber\PrepareVocabularyGraphSubscriber::alterEgovEra`
method.

### Importing eGovERA

In order to import the file anew - in case a new version is provided - you can
run the following deploy script:
```php
use EasyRdf\Graph;

$graph = new Graph();
$graph->parseFile('../resources/vocab/eGovERA.rdf');
\Drupal::service('joinup_taxonomy.vocabulary_updater')
  ->registerGraph('http://data.europa.eu/dr8', $graph)
  ->registerVocabulary('egovera', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
  ->update();
```

## EIRA and Solution type

EIRA vocabulary is the official vocabulary for the EIRA community. However,
because it is updated frequently, and each time there are conflicts that
consume a lot of development time to resolve, we are using a simplified version
of the EIRA vocabulary which we are - for now - basing it on version 6.0.0.

The goals of the simplified version of the EIRA vocabulary (Solution type) are:
1. To have a stable version of the EIRA vocabulary that does not change that
frequently.
2. To maintain the same structure as the EIRA vocabulary.
3. To maintain the data integrity on the Virtuoso endpoint.

Point 3 above is important because, using the `rdf_sync` module, we are syncing
the data to the Virtuoso endpoint. That means that the Solution type vocabulary
needs to have a mapping that is the same with EIRA, but without losing EIRA
data both from the EIRA vocabulary and the Solution type. In order to achieve
that, and because `rdf_sync` enforces unique URIs in the table, we are changing
the URIs of the Solution type vocabulary to be different from the EIRA by
adding the version in the URI, i.e. all URIs start with
`http://data.europa.eu/dr8/6.0.0/`.

A sample deploy script would look like this:
```php
  // Import the simplified EIRA 6.0.0.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8/6.0.0', $graph)
    ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8/6.0.0', 'http://data.europa.eu/dr8/6.0.0')
    ->update();

  // Import EIRA which will clear previous values.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
```

## Alterations

The module provides a subscriber that alters the EIRA, eGovERA and
solution_type vocabularies to add the necessary properties and fields. The
subscriber is located in
`\Drupal\joinup_taxonomy\EventSubscriber\PrepareVocabularyGraphSubscriber`
and the methods used are:
* `alterEira`
* `alterEgovEra`
* `alterSolutionType`

Each method is responsible to clean the graph and add the necessary properties
and fields.

For the cleanup, we take into account the base URI of the vocabulary and we
remove all the triples that are not related to the vocabulary. That is because
EIRA is packing their files with all the necessary relations, thus, adding
concepts that are not really part of the vocabulary but referenced by it.
