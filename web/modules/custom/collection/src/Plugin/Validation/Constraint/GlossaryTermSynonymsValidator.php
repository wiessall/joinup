<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Entity\GlossaryTermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Provides a validator for the GlossaryTermSynonyms constraint.
 */
class GlossaryTermSynonymsValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a new constraint validator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if ($value->isEmpty()) {
      return;
    }

    $glossaryTerm = $value->getEntity();
    $fieldDefinition = $value->getFieldDefinition();
    if (!$glossaryTerm instanceof GlossaryTermInterface || $fieldDefinition->getName() !== 'field_glossary_synonyms') {
      throw new \InvalidArgumentException("The 'GlossaryTermSynonyms' can be used only on field 'field_glossary_synonyms' of glossary terms.");
    }

    $labelLowercase = \mb_strtolower($glossaryTerm->label());
    $synonyms = [];
    foreach ($value as $item) {
      $synonymLowercase = \mb_strtolower($item->value);
      if ($synonymLowercase === $labelLowercase) {
        // Do not allow synonyms with the same name as the glossary term name.
        $this->context->addViolation($constraint->messageSameAsTermName, [
          '%name' => $glossaryTerm->label(),
        ]);
      }
      if (in_array($synonymLowercase, $synonyms, TRUE)) {
        // Don't allow synonym duplication.
        $this->context->addViolation($constraint->messageDuplicate, [
          '%synonym' => $item->value,
        ]);
      }
      $synonyms[$synonymLowercase] = $item->value;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'glossary')
      ->condition('og_audience', $glossaryTerm->get('og_audience')->target_id);
    if ($glossaryTermId = $glossaryTerm->id()) {
      // On update, filter out the current entity.
      $query->condition('nid', $glossaryTermId, '<>');
    }
    $nids = array_values($query->execute());

    $otherSynonyms = [];
    foreach ($storage->loadMultiple($nids) as $otherGlossaryTerm) {
      foreach ($otherGlossaryTerm->get('field_glossary_synonyms') as $item) {
        // We do a case-insensitive match.
        $otherSynonyms[\mb_strtolower($item->value)] = $otherGlossaryTerm;
      }
    }

    $usedSynonyms = [];
    foreach ($otherSynonyms as $otherGlossaryTerm) {
      $otherLabelLowercase = \mb_strtolower($otherGlossaryTerm->label());
      if (isset($synonyms[$otherLabelLowercase])) {
        $usedSynonyms[$otherLabelLowercase] = [
          'synonym' => $synonyms[$otherLabelLowercase],
          'entity' => $otherGlossaryTerm,
        ];
      }
      foreach ($otherGlossaryTerm->get('field_glossary_synonyms') as $item) {
        $otherGlossarySynonymLowercase = \mb_strtolower($item->value);
        if (isset($synonyms[$otherGlossarySynonymLowercase])) {
          $usedSynonyms[$otherGlossarySynonymLowercase] = [
            'synonym' => $synonyms[$otherGlossarySynonymLowercase],
            'entity' => $otherGlossaryTerm,
          ];
        }
      }
    }

    if ($usedSynonyms) {
      $args = [];
      $delta = 0;
      $occurrences = [];
      // Build a readable list of already used synonyms.
      foreach ($usedSynonyms as $data) {
        $synonymPlaceholder = "%synonym_{$delta}";
        $urlPlaceholder = ":url_{$delta}";
        $labelPlaceholder = "@label_{$delta}";
        $occurrences[] = "{$synonymPlaceholder} in <a href=\"{$urlPlaceholder}\">{$labelPlaceholder}</a>";
        $args += [
          $synonymPlaceholder => $data['synonym'],
          $urlPlaceholder => $data['entity']->toUrl()->toString(),
          $labelPlaceholder => $data['entity']->label(),
        ];
        $delta++;
      }
      $this->context->addViolation($constraint->messageInOtherTerms . implode(', ', $occurrences) . '.', $args);
    }
  }

}
