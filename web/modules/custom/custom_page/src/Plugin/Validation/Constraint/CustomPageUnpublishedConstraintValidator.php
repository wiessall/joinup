<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\custom_page\CustomPageProviderInterface;
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
    /** @var \Drupal\custom_page\Entity\CustomPageInterface $entity */
    $entity = $value->getEntity();
    if (!$entity->isPublished()) {
      if (!$entity->isGroupLandingPage()) {
        $this->context->addViolation(CustomPageProviderInterface::ERROR_MESSAGE, ['%s' => $entity->getGroup()->label()]);
      }
    }
  }

}
