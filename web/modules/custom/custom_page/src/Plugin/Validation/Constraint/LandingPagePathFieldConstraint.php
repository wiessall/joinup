<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the alias does not belong to restricted list.
 */
#[Constraint(
  id: 'LandingPagePathFieldConstraint',
  label: new TranslatableMarkup('Landing page path constraint', [], ['context' => 'Validation']),
  typs: "string"
)]
class LandingPagePathFieldConstraint extends Constraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public string $message = '@alias can not be used for URL alias field';

}
