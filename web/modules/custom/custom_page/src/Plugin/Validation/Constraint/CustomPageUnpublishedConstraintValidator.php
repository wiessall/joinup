<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Custom page unpublished constraint validator.
 */
class CustomPageUnpublishedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\custom_page\Entity\CustomPageInterface $custom_page */
    $custom_page = $value->getEntity();
    if (!$custom_page->isPublished()) {
      if (!$custom_page->isGroupLandingPage()) {
        $this->context->addViolation($constraint->message, ['@group' => $custom_page->getGroup()->get('rid')->entity->getSingularLabel()]);
      }
    }
  }

}
