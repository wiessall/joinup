<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sti_rdf\Service\AnnotationLibrary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders one facet block per field in the collection's schema.
 *
 * GET-method form: filter state lives in the URL, so it's bookmarkable and
 * the browser back button works. We don't process the submit; we just rely
 * on the browser turning checked values into query params.
 */
final class CollectionSearchForm extends FormBase {

  public function __construct(
    protected readonly AnnotationLibrary $library,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('sti_rdf.annotation_library'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_rdf_collection_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?string $collection = NULL,
    ?array $schema = NULL,
    ?array $filters = NULL,
  ): array {
    $schema ??= [];
    $filters ??= [];

    $form['#method'] = 'get';
    // GET forms don't need a CSRF token (and Drupal will complain if we try).
    $form['#token'] = FALSE;
    $form['#cache']['contexts'][] = 'url.query_args';

    // Each facet field.
    foreach ($schema as $fieldName) {
      $values = $this->library->getFacetValues($collection, $fieldName, $filters);
      if (empty($values)) {
        continue;
      }

      $options = [];
      foreach ($values as $v) {
        $options[$v['value']] = sprintf('%s (%d)', $v['label'], $v['count']);
      }

      $form[$fieldName] = [
        '#type' => 'checkboxes',
        '#title' => $this->humanize($fieldName),
        '#options' => $options,
        '#default_value' => $filters[$fieldName] ?? [],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply'),
      ],
    ];

    if (!empty($filters)) {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('sti_rdf.collection_search', ['collection' => $collection]),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No-op: GET form, browser handles state via URL.
  }

  /**
   * Turn `field_innovation_type` into `Innovation type` for display.
   */
  private function humanize(string $machineName): string {
    $s = preg_replace('/^field_/', '', $machineName);
    $s = str_replace('_', ' ', $s);
    return ucfirst($s);
  }

}
