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
`\Drupal\joinup_taxonomy\EventSubscriber\PrepareVocabularyGraphSubscriber::alterEira`
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
that, and because `rdf_sync` enforces unique URIs in the table, we need to:
* Make sure that on import, both vocabularies are imported.
* Make sure that there are no conflicts between the vocabularies causing RDF
sync to crash.
* Make sure that each time the EIRA vocabulary is updated, there are no missing
terms from any of the two vocabularies.

That means, that importing the EIRA or the Solution type vocabulary, requires
the following steps:
* Clean all terms of the EIRA and Solution type vocabularies from the
`rdf_sync_uri` table.
* Import the EIRA vocabulary.
* Import the Solution type vocabulary but skip the terms that are already in
the `rdf_sync_uri` table.

Including the deletion step, a sample deploy script would look like this:
```php
\Drupal::database()
  ->delete('rdf_sync_uri')
  ->condition('bundle', ['eira', 'solution_type'], 'IN')
  ->execute();

// Import EIRA first.
$graph = new Graph();
$graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
\Drupal::service('joinup_taxonomy.vocabulary_updater')
  ->registerGraph('http://data.europa.eu/dr8', $graph)
  ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
  ->update();

// Import the simplified EIRA 6.0.0.
$graph = new Graph();
$graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
\Drupal::service('joinup_taxonomy.vocabulary_updater')
  ->registerGraph('http://data.europa.eu/dr8', $graph)
  ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
  ->update();
```

## Alterations to the incoming data

As with other vocabularies, the EIRA and eGovERA vocabularies have their data
altered before being imported into the Portal.

## Notes

All 3 vocabularies are using the same namespace, which is
`http://data.europa.eu/dr8`. That means that there are going to be overrides in
the `rdf_sync_uri`. Keeping the sequence of import to be EIRA > Solution type >
eGovERA, we are making sure that the EIRA vocabulary is correctly set, and
any possible override of the Solution type and eGovERA vocabularies is
reflected back to the EIRA vocabulary, putting the burden to the EIRA team to
maintain a proper structure of the file.

Thus, it is suggested that any update in the EIRA or eGovERA vocabularies would
result in re-importing all 3 vocabularies as below:
```php
<?php

use EasyRdf\Graph;

/**
 * Deploy EIRA 6.1.0, simplified solution type (EIRA 6.0.0) and eGovERA.
 */
function joinup_core_deploy_200200(): void {
  // Import EIRA first.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  // Import the simplified EIRA 6.0.0.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  // Import eGovERA.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/eGovERA.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('egovera', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
}
```
