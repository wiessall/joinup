<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\custom_page\LandingPageAliasValidatorInterface;
use Drupal\path\Plugin\Field\FieldType\PathItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Landing page path field constraint validator.
 */
class LandingPagePathFieldConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an object.
   *
   * @param \Drupal\custom_page\LandingPageAliasValidatorInterface $aliasValidator
   *   Landing page alias validator.
   */
  public function __construct(
    protected LandingPageAliasValidatorInterface $aliasValidator
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('custom_page.landing_page_alias_validator')
    );
  }

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
    if (!$this->aliasValidator->isValid($alias)) {
      $this->context->addViolation($constraint->message, ['@alias' => $alias]);
    }
  }

}
