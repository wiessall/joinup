<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
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
      $node->type = $type;

      // This must be a new node.
      if (
        !isset($node->default_translation)
        || !empty($node->default_translation)
      ) {
        $this->nodeCreate($node);
        $lastId = $node->nid;

        continue;
      }

      // If we made it here, then we will add a new translation to the
      // previously created node.
      \assert(isset($lastId));
      \assert(isset($node->langcode));
      \assert(isset($availableLanguages[$node->langcode]));

      $original = $storage->load($lastId);
      \assert($original instanceof NodeInterface);
      $this->parseEntityFields('node', $node);
      $translation = array_filter(
        array_filter((array) $node) + $original->toArray(),
        fn (string|array $value): bool => $value !== '~~~' && $value !== ['~~~']
      );
      $original->addTranslation($node->langcode, $translation)->save();
    }
  }

  /**
   * Transforms string field values with "!multiline!" notation to be multiline.
   *
   * Kinda useful if we want to put a YAML file content into a text field, but
   * please be aware of that the string cannot contain ":" and "-" chars
   * surrounded by whitespace, because they have a specion meaning in the behat
   * context we use. So use " 0: Foo\n 1: Bar" instead of the " - Foo\n - Bar"
   * block notation.
   * @code
   * | title      | text_long field                                   |
   * | Node title | !multiline!root:\n  foo: {  }\n  bar:\n    0: Baz |
   * @endcode
   * The value of the text_long field above will be converted to the following
   * string:
   * @code
   * root:
   *   foo: {  }
   *   bar:
   *     0: Baz
   * @encode
   *
   * @BeforeNodeCreate
   */
  public function multilinePrepare(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();

    foreach ($node as $key => $value) {
      if (!is_string($value) || !str_starts_with($value, '!multiline!')) {
        continue;
      }
      $multiline = preg_replace('#\\\\n#', PHP_EOL, substr($value, 11));
      $node->$key = $multiline;
    }
  }

}
