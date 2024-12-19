<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\Boolean;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom Views field to display whether the user has authored content (nodes).
 */
#[ViewsField("joinup_user_is_author")]
class JoinupUserIsAuthor extends Boolean {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
    $this->field_alias = $this->table . '_' . $this->field;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL): bool {
    $user = $values->_entity;
    \assert($user instanceof UserInterface);
    return (bool) $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('uid', $user->id())
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
