<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\path\Plugin\Field\FieldType\PathItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Landing page path field constraint validator.
 */
class LandingPagePathFieldConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof PathItem) {
      return;
    }
    $customPage = !empty($value->getParent()) ? $value->getEntity() : NULL;
    if (!$customPage instanceof CustomPageInterface || $customPage->hasGroup()) {
      return;
    }

    $alias = $value->get('alias')->getString();
    if (!$this->isValid($alias)) {
      $this->context->addViolation($constraint->message, ['@alias' => $alias]);
    }
  }

  /**
   * Checks if alias is valid.
   *
   * @param string $alias
   *   Alias.
   *
   * @return bool
   *   True if it is valid.
   */
  protected function isValid(string $alias): bool {
    $paths = [
      '/search',
      '/topic',
      '/contact',
      '/collection',
      '/user',
      '/dashboard',
      '/interoperable-europe',
    ];

    foreach ($paths as $path) {
      if ($alias === $path || str_starts_with($alias, $path . '/')) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
