<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Plugin implementation of the 'private_message_actions' field.
 *
 * @ViewsField("private_message_actions")
 */
class PrivateMessageActions extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;

    if ($entity->getEntityTypeId() === 'private_message') {

      $operations['delete'] = [
        'title' => 'Delete',
        'url' => Url::fromRoute('entity.private_message.delete', ['private_message' => $entity->id()])->setOption('query', ['destination' => '/user/messages']),
        'attributes' => _joinup_private_message_get_ajax_modal_attributes(),
        'weight' => 0,
      ];
      $operations['flag_as_ok'] = [
        'title' => $this->t('Flag as OK'),
        'url' => Url::fromRoute('joinup_private_message.flag_as_ok', ['private_message' => $entity->id()])->setOption('query', ['destination' => '/dashboard/private-message-report']),
        'attributes' => _joinup_private_message_get_ajax_modal_attributes(),
        'weight' => 1,
      ];

      $build = [
        '#type' => 'operations',
        '#links' => $operations,
      ];

      return $build;
    }

    return '';
  }

}
