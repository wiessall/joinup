<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Serialization\Yaml;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Restore distributions.
 */
function joinup_rdf_deploy_110406(array &$sandbox): string {
  $map = [
    'http://joinup.eu/ad/workflow_status' => 'field_state',
    'http://joinup.eu/rdf_entity/group' => 'og_audience',
    'http://purl.org/dc/terms/description' => 'description',
    'http://purl.org/dc/terms/format' => 'format',
    'http://purl.org/dc/terms/issued' => 'created',
    'http://purl.org/dc/terms/license' => 'field_licence',
    'http://purl.org/dc/terms/modified' => 'changed',
    'http://purl.org/dc/terms/title' => 'title',
    'http://www.w3.org/ns/adms#representationTechnique' => 'representation_technique',
    'http://www.w3.org/ns/adms#status' => 'process_status',
    'http://www.w3.org/ns/dcat#accessURL' => 'access_url',
    'https://schema.org/fileSize' => 'file_size',
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['files'])) {
    $fileSystem = \Drupal::getContainer()->get('file_system');
    $sandbox['files'] = array_keys($fileSystem->scanDirectory('private://distributions', '/\.yml$/'));
    $sandbox['progress'] = 0;
  }

  $file = array_shift($sandbox['files']);
  $distributions = Yaml::decode(file_get_contents($file));
  foreach ($distributions as $uri => $data) {
    // Data integrity: Some distributions lack essential data. Skip them until
    // they are fixed in ISAICP-8649.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8649
    if (empty($data['http://purl.org/dc/terms/title'][0]['value'])) {
      if ($groupUri = ($data['http://joinup.eu/rdf_entity/group'][0]['value'] ?? NULL)) {
        // Remove the reverse reference, if case.
        $group = Rdf::load($groupUri);
        $distributionFieldName = $group instanceof SolutionInterface ? 'field_is_distribution' : 'field_isr_distribution';
        if (!$group->hasField($distributionFieldName)) {
          continue;
        }
        $field = $group->get($distributionFieldName);
        $initial = $field->getString();
        $group->get($distributionFieldName)->filter(function (EntityReferenceItem $item) use ($uri): bool {
          return $item->target_id !== $uri;
        });
        $final = $field->getString();
        if ($initial != $final) {
          $group->save();
        }
      }
      continue;
    }

    $values = ['type' => 'distribution', 'uri' => $uri];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          // Referential integrity issue: The referred licence is missing. Avoid
          // data inconsistency.
          // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8649
          if ($fieldName === 'field_licence') {
            if (!MappedTerm::loadByUri($value)) {
              continue;
            }
          }
          $value = match ($fieldName) {
            'field_licence', 'format', 'representation_technique' => MappedTerm::loadByUri($value)->id(),
            'description' => ['value' => $value, 'format' => 'content_editor'],
            'changed', 'created' => strtotime($value),
            // Title can't be longer than 255 characters.
            'title' => substr($value, 0, 255),
            default => $value,
          };
          $values[$fieldName][] = $value;
        }
      }
    }
    $storage->create($values)->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['files']);
  return "Imported {$sandbox['progress']} distributions";
}
