<?php

declare(strict_types=1);

namespace Drupal\joinup_acceptance\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_acceptance\Form\AcceptanceTasksForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block containing an acceptance tasks form.
 *
 * @Block(
 *   id = "acceptance_tasks",
 *   admin_label = @Translation("Acceptance tasks"),
 *   category = @Translation("Forms"),
 * )
 */
class AcceptanceTasksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'perform acceptance tasks');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->formBuilder->getForm(AcceptanceTasksForm::class);
  }

}
