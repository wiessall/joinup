<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessage;
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
  public function query() {
    // Do nothing -- to override the parent query.
  }

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
      $operations['flag_as_ok'] = [
        'title' => $this->t('Flag as OK'),
        'url' => Url::fromRoute('joinup_private_message.flag_as_ok', ['private_message' => $entity->id()]),
        'weight' => 0,
      ];

      $operations['delete'] = [
        'title' => 'Delete',
        'url' => Url::fromRoute('entity.private_message.delete', ['private_message' => $entity->id()]),
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

  /**
   * Submit handler for the Flag as OK operation.
   */
  public function submitFlagAsOk(array &$form, FormStateInterface $form_state): void {
    $private_message_id = $form['#private_message'];
    $private_message = PrivateMessage::load($private_message_id);

    if ($private_message) {
      // Clear the report_text and report_timestamp fields.
      $private_message->set('report_text', '');
      $private_message->set('report_timestamp', NULL);
      $private_message->save();

      // Add a message to the user.
      drupal_set_message($this->t('The message has been flagged as OK.'));
    }
  }

}
