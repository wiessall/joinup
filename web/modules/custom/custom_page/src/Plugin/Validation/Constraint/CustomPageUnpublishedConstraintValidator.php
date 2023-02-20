<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\custom_page\CustomPageProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Custom page unpublished constraint validator.
 */
class CustomPageUnpublishedConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a new constraint validator instance.
   *
   * @param \Drupal\custom_page\CustomPageProviderInterface $custom_page_provider
   *   The custom page provider service.
   */
  public function __construct(public CustomPageProviderInterface $custom_page_provider) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('custom_page.provider'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $entity = $value->getEntity();
    if (!$entity->isPublished()) {
      if (!$this->custom_page_provider->validateLandingPage($entity)) {
        $this->context->addViolation(CustomPageProviderInterface::ERROR_MESSAGE, ['%s' => $entity->getGroup()->label()]);
      }
    }
  }

}
