<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\EntityBrowser\Widget;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\entity_browser_entity_form\Plugin\EntityBrowser\Widget\EntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * EntityForm widget with extended access control logic.
 *
 * Access is granted if the user has entity browser permission; otherwise,
 * the standard node membership check is applied.
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
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    // Don't let IEF save the entity on its own. We save it in submit() only
    // after validation passes.
    if (isset($form['inline_entity_form'])) {
      $form['inline_entity_form']['#save_entity'] = FALSE;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state): void {
    parent::validate($form, $form_state);
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $widget_form = $form[$form['#browser_parts']['widget']];
      if (isset($widget_form['inline_entity_form']['#entity'])) {
        $entity = $widget_form['inline_entity_form']['#entity'];
        $form_display = $this->entityDisplayRepository->getFormDisplay(
          $this->configuration['entity_type'],
          $this->configuration['bundle'],
          $this->configuration['form_mode'],
        );
        $form_display->validateFormValues($entity, $widget_form['inline_entity_form'], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state): void {
    if ($form_state->hasAnyErrors()) {
      return;
    }
    parent::submit($element, $form, $form_state);
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
