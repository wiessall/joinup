<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\node\NodeInterface;

/**
 * Creates multilingual content.
 */
class JoinupMultilingualContentContext extends RawDrupalContext {

  /**
   * Creates content and also adds translations if any.
   *
   * Creates content of a given type and adds translations provided in the form:
   * @code
   * | title        | author      | langcode | default_translation |
   * | RO title     | Joe Editor  | ro       | 1                   |
   * | HU title     |             | hu       | 0                   |
   * | Another node | Jane Editor | it       | 1                   |
   * | ...          | ...         | ...      | ...                 |
   * @endcode
   *
   * Use string "~~~" to explicitly set a NULL value (so the value of the
   * default translation won't be copied into the newly added translation).
   *
   * @Given :type multilingual content:
   */
  public function createMultilingualContent($type, TableNode $nodesTable): void {
    $lastId = NULL;
    $availableLanguages = \Drupal::languageManager()->getLanguages();
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;

      // @todo Consider creating a standalone command for adding translation.
      if ($node->default_translation === '0') {
        \assert(isset($lastId));
        \assert(isset($node->langcode));
        \assert(isset($availableLanguages[$node->langcode]));
        $original = $storage->load($lastId);
        \assert($original instanceof NodeInterface);
        $this->parseEntityFields('node', $node);
        $translation = array_filter(
          array_filter((array) $node) + $original->toArray(),
          function (string|array $value) {
            return is_string($value) || $value !== ['~~~'];
          }
        );
        $original->addTranslation($node->langcode, $translation)->save();
        continue;
      }

      $node->type = $type;
      $this->nodeCreate($node);
      $lastId = $node->nid;
    }
  }

}
