<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_collection\Entity\GlossaryTermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Provides a validator for the GlossaryTermName constraint.
 */
class GlossaryTermNameValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (!$glossaryTerm instanceof GlossaryTermInterface || $fieldDefinition->getName() !== 'title') {
      throw new \InvalidArgumentException("The 'GlossaryTermName' can be used only on field 'title' of glossary terms.");
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

    $synonyms = [];
    foreach ($storage->loadMultiple($nids) as $otherGlossaryTerm) {
      foreach ($otherGlossaryTerm->get('field_glossary_synonyms') as $item) {
        // We do a case-insensitive match.
        $synonyms[\mb_strtolower($item->value)] = $otherGlossaryTerm;
      }
    }

    $lowerCaseLabel = \mb_strtolower($glossaryTerm->label());
    if (isset($synonyms[$lowerCaseLabel])) {
      $this->context->addViolation($constraint->message, [
        '%name' => $glossaryTerm->label(),
        ':url' => $synonyms[$lowerCaseLabel]->toUrl()->toString(),
        '@glossary' => $synonyms[$lowerCaseLabel]->label(),
      ]);
    }
  }

}
