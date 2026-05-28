<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Prevents archiving a collection that still has published solutions.
 */
#[Constraint(
  id: 'CollectionArchivePublishedSolutions',
  label: new TranslatableMarkup('Collection archive with published solutions', [], ['context' => 'Validation']),
)]
class CollectionArchivePublishedSolutions extends SymfonyConstraint {

  public string $message = 'Collection has at least one published solution. Please archive all active solutions first.';

}