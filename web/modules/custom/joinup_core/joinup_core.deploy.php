<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\Core\Field\FormatterPluginManager;
use Drupal\smart_trim\Plugin\Field\FieldFormatter\SmartTrimFormatter;
use Drupal\smart_trim\TruncateHTML;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageInterface;

/**
 * Pre-populate solution abstract fields.
 */
function joinup_core_deploy_0199201(array &$sandbox = []): string {
  $rdfStorage = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity');
  \assert($rdfStorage instanceof SparqlEntityStorageInterface);
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = array_values(
      $rdfStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('rid', 'solution')
        ->execute()
    );
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['processed_count'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 50);
  $formatterPluginManager = \Drupal::service('plugin.manager.field.formatter');
  \assert($formatterPluginManager instanceof FormatterPluginManager);
  $truncate = \Drupal::service('smart_trim.truncate_html');
  \assert($truncate instanceof TruncateHTML);
  $descriptionFieldName = NULL;
  $smartTrim = NULL;
  foreach ($rdfStorage->loadMultiple($ids) as $solution) {
    \assert($solution instanceof SolutionInterface);
    $descriptionFieldName = $descriptionFieldName ?? $solution->getDescriptionField();
    $descriptionItemList = $solution->get($descriptionFieldName);
    if ($descriptionItemList->isEmpty()) {
      continue;
    }
    $smartTrim = $smartTrim ?? $formatterPluginManager->createInstance(
      'smart_trim',
      [
        'field_definition' => $descriptionItemList->getFieldDefinition(),
        'settings' => [
          'trim_length' => 200,
          'trim_type' => 'chars',
          'trim_suffix' => '…',
          'wrap_output' => FALSE,
          'more' => ['display_link' => FALSE],
          'summary_handler' => 'trim',
          'trim_options' => [
            'text' => 'text',
            'replace_tokens' => 'replace_tokens',
          ],
        ],
        'label' => 0,
        'view_mode' => '_custom',
        'third_party_settings' => [],
      ]
    );
    \assert($smartTrim instanceof SmartTrimFormatter);

    $abstractRenderable = $smartTrim->viewElements($solution->get($descriptionFieldName));
    $abstract = $abstractRenderable[0]['#output']['#text'] ?? '';
    // CR is left in place in form of its HTML-encoded format "&#13;".
    $abstract = trim(
      preg_replace(
        ['/&#13;/', '/\s+/', '/[[:punct:]]+…$/'],
        ['', ' ', '…'],
        $abstract
      )
    );

    // "test solution, not for approval" throws an error during update, because
    // the file it is referencing from the "field_is_documentation" field is
    // missing (not the asset, but the file entity). The UUID of the missing
    // file is "07b7cd2d-936d-4f33-80ee-791e1e828a0c".
    try {
      $solution
        ->set('field_is_abstract', $abstract)
        ->save();
    }
    catch (\Throwable | \Exception) {
    }
  }

  $sandbox['processed_count'] += count($ids);
  $sandbox['#finished'] = $sandbox['processed_count'] / $sandbox['total'];
  return "Finished updating {$sandbox['processed_count']} of {$sandbox['total']} solutions.";
}
