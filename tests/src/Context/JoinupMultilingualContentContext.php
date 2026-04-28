<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\node\NodeInterface;

/**
 * Creates multilingual content.
 */
class JoinupMultilingualContentContext extends RawDrupalContext {

  use EntityTrait;

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
   */
  #[Given(':type multilingual content:')]
  public function createMultilingualContent($type, TableNode $nodesTable): void {
    $lastId = NULL;
    $availableLanguages = \Drupal::languageManager()->getLanguages();
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;
      $node->type = $type;

      // Read and strip the step-routing column before Drupal field validation.
      $isDefaultTranslation = !isset($node->default_translation) || !empty($node->default_translation);
      unset($node->default_translation);

      // This must be a new node.
      if ($isDefaultTranslation) {
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
      $node = (object) array_filter((array) $node, fn (mixed $value): bool => $value !== '~~~' && $value !== ['~~~']);

      // This will eventually translate field aliases before expanding fields.
      // @see \Drupal\joinup\Context\JoinupContext::massageFieldsBeforeNodeCreate
      $this->dispatchHooks(BeforeNodeCreateScope::class, $node);
      $translation = array_filter((array) $node) + $original->toArray();
      $original->addTranslation($node->langcode, $translation)->save();
    }
  }

  /**
   * Creates a translation for an arbitrary entity.
   *
   * @param string $langcode
   *   The language of the translation.
   * @param string $entityTypeId
   *   The entity type ID of the entity translated.
   * @param string $label
   *   The untranslated label of the entity translated.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   An arbitrary list of fields to pass to the translation.
   */
  #[Given(':langcode translation for the :entityTypeId entity with label :label:')]
  public function createTranslationForEntity(string $langcode, string $entityTypeId, string $label, TableNode $fields): void {
    $entity = $this->getEntityByLabel($entityTypeId, $label);
    if (!$entity instanceof TranslatableInterface) {
      throw new \RuntimeException(sprintf('The entity with label "%s" is not translatable.', $label));
    }

    $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity->addTranslation($langcode);
    foreach ($fields->getHash() as $field) {
      $translation->set($field['field'], $field['value']);
    }
    $translation->save();
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
   */
  #[BeforeNodeCreate]
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
