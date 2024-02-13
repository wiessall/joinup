<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\custom_page\Entity\CustomPageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Custom page unpublished constraint validator.
 */
class CustomPageUnpublishedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    $custom_page = $value->getEntity();
    if ($custom_page instanceof CustomPageInterface) {
      if (!$custom_page->isPublished() && $custom_page->isGroupLandingPage()) {
        $this->context->addViolation($constraint->message, ['@group' => $custom_page->getGroup()->get('rid')->entity->getSingularLabel()]);
      }
    }
  }

}
