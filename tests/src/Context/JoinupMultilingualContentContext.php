<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\babel\BabelStorageInterface;
use Drupal\babel\Model\Source;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\node\NodeInterface;

/**
 * Creates multilingual content.
 */
class JoinupMultilingualContentContext extends RawDrupalContext {

  use EntityTrait;
  use TraversingTrait;

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
   * Adds locale string translations via Babel.
   *
   * Creates locale source strings and their translations programmatically,
   * replicating what the Babel translate UI does. Accepts a table in the form:
   * @code
   * | source          | language | translation         | status |
   * | My term         | fr       | Mon terme           | 1      |
   * | Another string  | de       | Ein anderer Begriff |        |
   * @endcode
   *
   * The "status" column is optional and defaults to 1 (active). A value of 0
   * marks the string as inactive in the Babel UI.
   */
  #[Given('the following locale string translations:')]
  public function addLocaleStringTranslations(TableNode $table): void {
    $localeStorage = \Drupal::service('locale.storage');
    $babelStorage = \Drupal::service(BabelStorageInterface::class);

    $lidsToRefresh = [];
    $langcodesToRefresh = [];
    foreach ($table->getHash() as $row) {
      $sourceText = $row['source'];
      $langcode = $row['language'];
      $translation = $row['translation'];
      $status = isset($row['status']) && $row['status'] !== '' ? (bool) $row['status'] : TRUE;

      // Find or create the source string in locales_source.
      $localeString = $localeStorage->findString(['source' => $sourceText]);
      if (!$localeString) {
        $localeString = $localeStorage->createString(['source' => $sourceText, 'context' => '']);
        $localeStorage->save($localeString);
      }

      $lid = $localeString->getId();

      // Create or update the translation in locales_target.
      $existing = $localeStorage->findTranslation(['lid' => $lid, 'language' => $langcode]);
      if ($existing) {
        $existing->setValues(['translation' => $translation, 'customized' => LOCALE_CUSTOMIZED, 'language' => $langcode]);
        $localeStorage->save($existing);
      }
      else {
        $localeStorage->createTranslation([
          'lid' => $lid,
          'language' => $langcode,
          'translation' => $translation,
          'customized' => LOCALE_CUSTOMIZED,
        ])->save();
      }

      // Collect LIDs and language codes for cache refresh.
      $lidsToRefresh[$lid] = $lid;
      $langcodesToRefresh[$langcode] = $langcode;

      // Register the string in Babel's tracking tables, then apply the
      // requested status. BabelStorage::update() inserts with the plugin's
      // default status (TRUE for locale), so we always call
      // updateStatusForHash() afterwards to honour the requested value.
      $source = new Source($sourceText, '');
      $babelStorage->update('locale', [(string) $lid => $source]);
      $babelStorage->updateStatusForHash($source->getHash(), $status);
    }

    // Refresh the Babel cache for the affected strings. This might try to
    // refresh more than needed due to gathering LIDs and language codes in
    // distinct groups, but it is simpler and the volume is still lower.
    _locale_refresh_translations($langcodesToRefresh, $lidsToRefresh);
  }

  /**
   * Adds entity translations to existing entities.
   *
   * Looks up each entity by its label, then creates or updates an
   * entity-level translation for the given language.
   *
   * Example:
   *
   * @code
   * Given the following taxonomy term entity translations:
   *   | source            | language | name              |
   *   | Complete Solution | fr       | Solution complète |
   * Given the following glossary entity translations:
   *   | source   | language | title     | definition           |
   *   | Alphabet | fr       | Alphabète | Définition détaillée |
   * @endcode
   *
   * @param string $entityTypeAlias
   *   The entity type alias (e.g. 'node', 'taxonomy term', 'glossary').
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table of translations.
   *
   * @throws \Exception
   *   If the source entity or its label key is not found.
   */
  #[Given('the following :entityTypeAlias entity translation(s):')]
  public function addEntityTranslations(string $entityTypeAlias, TableNode $table): void {
    $entityTypeId = $this->translateEntityTypeAlias($entityTypeAlias);
    $storage = \Drupal::entityTypeManager()->getStorage($entityTypeId);
    $entityType = $storage->getEntityType();
    $labelKey = $entityType->getKey('label');

    if (!$labelKey) {
      throw new \Exception(sprintf("Entity type '%s' does not have a label key.", $entityType->id()));
    }

    foreach ($table->getHash() as $row) {
      if (!isset($row['source']) || !isset($row['language'])) {
        throw new \Exception("The 'source' and 'language' columns are mandatory.");
      }

      $entities = $storage->loadByProperties([$labelKey => $row['source']]);
      if (!$entities) {
        throw new \Exception(sprintf("Entity of type '%s' with label '%s' not found.", $entityType->id(), $row['source']));
      }

      $entity = reset($entities);
      \assert($entity instanceof ContentEntityInterface);

      if ($entity->hasTranslation($row['language'])) {
        $translated = $entity->getTranslation($row['language']);
      }
      else {
        $translated = $entity->addTranslation($row['language'], $entity->toArray());
      }

      $processedFields = ['source', 'language'];
      foreach ($row as $field => $value) {
        if (in_array($field, $processedFields)) {
          continue;
        }

        $fieldDefinition = $translated->getFieldDefinition($field);

        if (!$fieldDefinition) {
          throw new \InvalidArgumentException(sprintf("Field '%s' is unknown on entity type '%s'.", $field, $entityType->id()));
        }

        if ($fieldDefinition->getType() === 'text_with_summary') {
          $translated->set($field, [
            'value' => $value,
            'summary' => $row['summary'] ?? '',
            'format' => 'content_editor',
          ]);
          $processedFields[] = 'summary';
        }
        else {
          $translated->set($field, $value);
        }
      }

      // Ensure the translation is published if the source is published and the
      // entity type supports it.
      if ($entity instanceof EntityPublishedInterface && $entity->isPublished()) {
        $translated->setPublished();
      }

      $translated->save();
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

  /**
   * Visits a version of an entity in a given language.
   *
   * @param string $language
   *   The language name. Example: "el", "fr".
   * @param string $label
   *   The label of the entity.
   * @param string $bundle
   *   The entity bundle. Example: "collection", "solution".
   *
   * @throws \Exception
   *   Thrown when the entity is not found.
   */
  #[Given('I go to the :language version of the :label :bundle')]
  public function visitEntityInGivenLanguage(string $language, string $label, string $bundle): void {
    $entityTypeId = $this->translateEntityTypeAlias($bundle);
    $bundleId = $this->translateBundle($bundle);
    $entity = $this->getEntityByLabel($entityTypeId, $label, $bundleId);

    $url = $entity->toUrl();
    $url->setOption('language', \Drupal::languageManager()->getLanguage($language));
    $this->visitPath($url->toString());
  }

  /**
   * Switch the page to a language, given its langcode.
   */
  #[When('I switch to the :langcode language')]
  public function switchLanguage(string $langcode): void {
    $region = $this->getRegion('Top Bar');
    $link = $region->find('css', "a[hreflang='$langcode']");
    if (!$link) {
      throw new \RuntimeException("No language switch link found for '$langcode'.");
    }

    $this->visitPath($link->getAttribute('href'));
  }

}
