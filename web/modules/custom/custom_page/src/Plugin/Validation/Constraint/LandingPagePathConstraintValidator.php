<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\path_alias\PathAliasInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Landing page path constraint validator.
 */
class LandingPagePathConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an LandingPagePathConstraintValidator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

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
    if (!$value instanceof PathAliasInterface) {
      return;
    }

    $customPage = str_starts_with($value->getPath(), '/node/') ?
      $this->entityTypeManager
        ->getStorage('node')
        ->load(substr($value->getPath(), 6)) : NULL;
    if (!$customPage instanceof CustomPageInterface || $customPage->hasGroup()) {
      return;
    }

    if (!$this->isAliasValid($value->getAlias())) {
      $this->context->addViolation($constraint->message, ['@alias' => $value->getAlias()]);
    }
  }

  /**
   * Checks if alias is valid.
   *
   * @param string $alias
   *   Alias.
   *
   * @return bool
   *   True if it is allowed.
   */
  protected function isAliasValid(string $alias): bool {
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
