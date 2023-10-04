<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\custom_page\LandingPageAliasValidatorInterface;
use Drupal\path_alias\PathAliasInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Landing page path alias entity constraint validator.
 */
class LandingPagePathAliasEntityConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\custom_page\LandingPageAliasValidatorInterface $aliasValidator
   *   Landing page alias validator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LandingPageAliasValidatorInterface $aliasValidator
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('custom_page.landing_page_alias_validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof PathAliasInterface) {
      return;
    }

    $customPage = $value->getPath() && str_starts_with($value->getPath(), '/node/') ?
      $this->entityTypeManager
        ->getStorage('node')
        ->load(substr($value->getPath(), 6)) : NULL;
    if (!$customPage instanceof CustomPageInterface || $customPage->hasGroup()) {
      return;
    }

    if (!$this->aliasValidator->isValid($value->getAlias())) {
      $this->context->addViolation($constraint->message, ['@alias' => $value->getAlias()]);
    }
  }

}
