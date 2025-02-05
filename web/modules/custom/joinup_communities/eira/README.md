# EIRA Community Module

This module provides configuration for the EIRA Community.

## Introduction

The EIRA community hosts 2 vocabularies with us. The vocabularies are:
* EIRA
* eGovERA

Both vocabularies are imported into the Portal from the corresponding RDF files
in the `resources/vocab` directory.

Mappings are already set in third party settings of the vocabulary and related
fields. The module also offers a normalizer subscriber to handle extra
properties that are needed during import in
[/web/modules/custom/joinup_communities/eira/src/EventSubscriber/NormalizeSubscriber.php](src/EventSubscriber/NormalizeSubscriber.php).

## Importing the vocabularies

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
The above example is for eGovERA, but the same can be done for EIRA.

Note, that both vocabularies are using the same namespace, so the same URL is
used for both the vocabulary and the graph.

## Alterations to the incoming data

As with other vocabularies, the EIRA and eGovERA vocabularies have their data
altered before being imported into the Portal. The alterations are done in the
`\Drupal\joinup_taxonomy\EventSubscriber\PrepareVocabularyGraphSubscriber::alterEira`
method.
