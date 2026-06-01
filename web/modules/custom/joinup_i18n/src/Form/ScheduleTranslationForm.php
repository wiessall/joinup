<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Form;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_i18n\TranslationScheduler;

/**
 * Form to schedule translation for a collection and its content.
 */
class ScheduleTranslationForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TranslationScheduler $translationScheduler,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'schedule_translation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $submit = [
      '#type' => 'submit',
      '#value' => $this->t('Schedule translation'),
    ];

    $form['actions_top'] = ['#type' => 'actions', '#weight' => 0];
    $form['actions_top']['submit'] = $submit;

    $form['collection'] = [
      '#type' => 'tableselect',
      '#weight' => 1,
      '#header' => [
        'title' => $this->t('Collection'),
        'sent' => $this->t('Last sent'),
      ],
      '#options' => $this->getCollectionOptions(),
      '#multiple' => FALSE,
      '#empty' => $this->t('No collections found.'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions', '#weight' => 2];
    $form['actions']['submit'] = $submit;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $collection = $this->entityTypeManager->getStorage('node')
      ->load($form_state->getValue('collection'));
    assert($collection instanceof CollectionInterface);

    batch_set($this->translationScheduler->buildBatch($collection));
  }

  /**
   * Builds the tableselect options for the collection list.
   *
   * @return array
   *   Options keyed by collection node ID.
   */
  protected function getCollectionOptions(): array {
    $ids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'collection')
      ->condition('status', 1)
      ->condition('field_state', 'archived', '<>')
      ->sort('title')
      ->execute();

    $options = [];
    foreach ($this->entityTypeManager->getStorage('node')
      ->loadMultiple($ids) as $id => $collection) {
      $timestamp = $this->translationScheduler->getSentTimestamp((string) $id);
      $options[(string) $id] = [
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $collection->label(),
            '#url' => $collection->toUrl(),
          ],
        ],
        'sent' => $timestamp ? $this->dateFormatter->format($timestamp, 'short') : $this->t('Never'),
      ];
    }

    return $options;
  }

}
