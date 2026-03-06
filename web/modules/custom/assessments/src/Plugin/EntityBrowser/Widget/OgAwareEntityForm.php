<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\EntityBrowser\Widget;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\entity_browser_entity_form\Plugin\EntityBrowser\Widget\EntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Extends EntityForm widget with entity browser permission access checking.
 */
class OgAwareEntityForm extends EntityForm {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    WidgetValidationManager $validation_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $event_dispatcher,
      $entity_type_manager,
      $validation_manager,
      $entity_type_bundle_info,
      $entity_display_repository
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(): AccessResultInterface {
    $permission = 'access ' . $this->configuration['entity_browser_id'] . ' entity browser pages';
    $permission_access = AccessResult::allowedIfHasPermission($this->currentUser, $permission);
    if ($permission_access->isAllowed()) {
      return $permission_access;
    }

    return parent::access();
  }

}
