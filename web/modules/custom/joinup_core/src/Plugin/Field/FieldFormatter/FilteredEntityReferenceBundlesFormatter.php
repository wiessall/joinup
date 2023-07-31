<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'filtered entity reference bundles' formatter.
 *
 * @FieldFormatter(
 *   id = "filtered_entity_reference_bundles",
 *   label = @Translation("Filtered rendered entity bundles"),
 *   description = @Translation("Filtered referenced entities bundles."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class FilteredEntityReferenceBundlesFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->setEntityTypeBundleInfo($container->get('entity_type.bundle.info'));

    return $formatter;
  }

  /**
   * Sets entity type bundle info.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Entity type bundle info.
   */
  public function setEntityTypeBundleInfo(EntityTypeBundleInfoInterface $bundleInfo): void {
    $this->entityTypeBundleInfo = $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return array(
        'filtered_entity_bundles' => [],
      ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $entityTypeId = $this->getFieldSetting('target_type');
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

    $elements['filtered_entity_bundles'] = [
      '#type' => 'select',
      '#title' => $this->t('Select bundles which are allowed'),
      '#options' => array_combine(array_keys($bundles), array_column($bundles, 'label')),
      '#default_value' => $this->getSetting('filtered_entity_bundles'),
      '#required' => TRUE,
      '#multiple' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode): array {
    $entities = parent::getEntitiesToView($items, $langcode);
    $allowed = $this->getSetting('filtered_entity_bundles');

    return array_filter($entities, function (ContentEntityInterface $entity) use ($allowed) {
      return in_array($entity->bundle(), $allowed);
    });
  }

}
