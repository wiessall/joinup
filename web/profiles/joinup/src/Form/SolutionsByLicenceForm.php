<?php

declare(strict_types=1);

namespace Drupal\joinup\Form;

use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\joinup_licence\Entity\LicenceInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\og\OgGroupAudienceHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A reporting page that presents solutions indexed by related licences.
 */
class SolutionsByLicenceForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solutions_by_licence_form';
  }

  /**
   * Constructs a SolutionsByLicenceController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\mysql\Driver\Database\mysql\Connection $database
   *   The database service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $linkGenerator
   *   The link generator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LinkGeneratorInterface $linkGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (empty($form_state->get('licence_options'))) {
      $form_state->set('licence_options', $this->getLicenceOptions());
    }

    $form['licence_option'] = [
      '#type' => 'select',
      '#title' => $this->t('Licence'),
      '#options' => $form_state->get('licence_options'),
      '#default_value' => $form_state->getValue('licence_option'),
      '#empty_option' => ' - All - ',
    ];
    $form['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $licence_id = $form_state->getValue('licence_option');
    $query = $this->database->select('node_field_data', 'nfd');
    $query->extend(PagerSelectExtender::class);
    $query->leftJoin('node__field_licence', 'nfl', 'nfl.entity_id = nfd.nid');
    $query->leftJoin('node__' . OgGroupAudienceHelperInterface::DEFAULT_FIELD, 'nog', 'nog.entity_id = nfd.nid');
    $query->fields('nog', ['og_audience_target_id'])
      ->fields('nfl', ['field_licence_target_id'])
      ->groupBy('nog.og_audience_target_id')
      ->groupBy('nfl.field_licence_target_id');
    if ($licence_id) {
      $query->condition('nfl.field_licence_target_id', $licence_id);
    }

    $items = $query->execute()->fetchAll();

    $rows = [];
    foreach ($items as $item) {
      if (empty($item->field_licence_target_id) || empty($item->og_audience_target_id)) {
        continue;
      }

      $licence = $this->entityTypeManager->getStorage('node')->load($item->field_licence_target_id);
      $solution = $this->entityTypeManager->getStorage('rdf_entity')->load($item->og_audience_target_id);

      if (!$licence || !$solution) {
        continue;
      }

      $rows[] = [
        $this->linkGenerator->generate($licence->label(), $licence->toUrl()),
        $this->linkGenerator->generate($solution->label(), $solution->toUrl()),
      ];
    }

    $form['table'] = [
      '#theme' => 'table',
      '#header' => ['Licence', 'Solution'],
      '#rows' => $rows,
      '#empty' => $this->t('No solutions available.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The only case where the form is submitted is by clicking the filter.
    // Rebuild to filter by licence.
    $form_state->setRebuild();
  }

  /**
   * Returns the full list of licences in the website.
   *
   * @return array
   *   An array of licence labels indexed by licence id.
   */
  protected function getLicenceOptions(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'licence')
      ->execute();
    if (!$nids) {
      return [];
    }
    return array_map(
      fn(LicenceInterface $licence): string => $licence->label(),
      $storage->loadMultiple($nids),
    );
  }

}
