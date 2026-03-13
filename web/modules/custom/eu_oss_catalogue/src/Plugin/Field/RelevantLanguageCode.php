<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\eu_oss_catalogue\Entity\OssSolution;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Computed field with the richest entity translation language code.
 */
class RelevantLanguageCode extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * The field name of this field.
   *
   * This constant is used by RichestField instances.
   *
   * @const string
   *
   * @see \Drupal\eu_oss_catalogue\Plugin\Field\RichestField::computeValue()
   */
  const FIELD_NAME = 'oss__relevant_language';

  /**
   * Name of the field which is used to determine the richest translation.
   *
   * @const string
   */
  const FLAGSHIP_FIELD = 'oss_features';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $this->list[0] = $this->createItem(0, $this->getRelevantLanguageCode());
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @return string
   *   The language code of the richest entity translation.
   */
  protected function getRelevantLanguageCode(): string {
    $ossSolution = $this->getEntity();
    \assert($ossSolution instanceof OssSolution);
    $translationLanguages = $ossSolution->getTranslationLanguages();
    if (count($translationLanguages) === 1) {
      return key($translationLanguages);
    }

    // If publiccode.yml field does not contain any language codes, then we
    // continue with the existing entity translations. But if we have
    // publiccode.yml language codes, then we use the union of the pc.yml
    // language codes and the entity translation language codes.
    $translationLanguageCodes = empty($publicCodeLanguageCodes = $this->getLanguageCodesFromPublicCodeYaml())
      ? array_keys($translationLanguages)
      : array_intersect($publicCodeLanguageCodes, array_keys($translationLanguages));

    // Only one translation left, no need to perform the "richest" check.
    if (count($translationLanguageCodes) === 1) {
      return reset($translationLanguageCodes);
    }

    $flagshipFieldItemCounts = array_reduce(
      $translationLanguageCodes ?: array_keys($translationLanguages),
      function (array $carry, string $languageCode) use ($ossSolution): array {
        $translation = $ossSolution->getTranslation($languageCode);
        $carry[$languageCode] = !$translation->get(static::FLAGSHIP_FIELD)->isEmpty()
          ? (int) $translation->get(static::FLAGSHIP_FIELD)->count()
          : 0;
        return $carry;
      },
      []
    );

    // We have 2 translation language keys at least, so we won't return FALSE.
    return array_search(max($flagshipFieldItemCounts), $flagshipFieldItemCounts, TRUE);
  }

  /**
   * Returns language codes used in publiccode.yml.
   *
   * @return string[]
   *   The language codes used at the description property. Returns empty array
   *   if:
   *   - The solution has no field data at the field.
   *   - The data is not a valid YAML string (so it cannot be decoded).
   *   - The decoded content is not an array.
   *   - The decoded content lacks the description key, or its value is not an
   *     array, or if it is empty.
   */
  protected function getLanguageCodesFromPublicCodeYaml(): array {
    $ossSolution = $this->getEntity();
    \assert($ossSolution instanceof OssSolutionInterface);
    $publicCodeYamlContent = $ossSolution->getPublicCodeYaml();
    if (is_null($publicCodeYamlContent)) {
      return [];
    }

    try {
      $publicCodeYaml = Yaml::decode($publicCodeYamlContent);
    }
    catch (InvalidDataTypeException) {
      return [];
    }

    if (
      !is_array($publicCodeYaml)
      || empty($publicCodeYaml['description'])
      || !is_array($publicCodeYaml['description'])
    ) {
      return [];
    }

    return array_keys($publicCodeYaml['description']);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update): bool {
    // Force recompute after save.
    $this->valueComputed = FALSE;
    $this->list = [];
    return parent::postSave($update);
  }

}
