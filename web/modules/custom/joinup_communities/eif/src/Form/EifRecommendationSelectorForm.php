<?php

declare(strict_types=1);

namespace Drupal\eif\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form class for EIF recommendation selector.
 */
class EifRecommendationSelectorForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eif_recommendation_selector';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'eif_recommendation')
      ->execute();

    $options = [];
    foreach ($storage->loadMultiple($tids) as $tid => $term) {
      $options[$tid] = $this->t('Solutions implementing @recommendation', ['@recommendation' => $term->label()]);
    }
    natcasesort($options);

    $form['term'] = [
      '#type' => 'select',
      '#title' => $this->t('Jump to recommendation'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#empty_option' => $this->t('Filter Solutions by Recommendation'),
      '#attached' => [
        'library' => [
          'eif/recommendations.selector',
        ],
      ],
      '#attributes' => [
        'data-joinup-eif-recommendation-selector' => 'true',
      ],
      '#cache' => [
        'tags' => [
          'taxonomy_term_list:eif_recommendation',
        ],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Jump'),
      '#attributes' => [
        'class' => [
          'js-hide',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('entity.taxonomy_term.canonical', [
      'taxonomy_term' => $form_state->getValue('term'),
    ]);
  }

}
