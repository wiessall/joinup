<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action that changes the collection for solutions.
 *
 * @Action(
 *   id = "change_collection",
 *   label = @Translation("Move to other collection"),
 *   type = "node",
 *   confirm_form_route_name = "joinup_solution.change_collection",
 * )
 */
class ChangeCollectionAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Constructs a new 'change_group' action plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_tempstore_factory
   *   The private tempstore factory.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, PrivateTempStoreFactory $private_tempstore_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStore = $private_tempstore_factory->get('change_collection');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($solution, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResult|bool {
    // The access is limited at the view level.
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $solutions): void {
    $solution_ids = array_map(function (NodeInterface $solution): string {
      if ($solution->bundle() !== 'solution') {
        throw new \LogicException("The 'change_collection' action can be applied only to solutions. Got '{$solution->bundle()}' for ID '{$solution->id()}'.");
      }
      return $solution->id();
    }, $solutions);
    $this->tempStore->set('solutions', $solution_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?NodeInterface $solution = NULL): void {
    $this->executeMultiple([$solution]);
  }

}
