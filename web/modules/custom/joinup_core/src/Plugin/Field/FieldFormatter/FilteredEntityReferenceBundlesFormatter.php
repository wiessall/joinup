<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
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
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $formatter->setEntityTypeBundleInfo($container->get('entity_type.bundle.info'));
    $formatter->setRenderer($container->get('renderer'));

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
   * Sets renderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *    The renderer service.
   */
  public function setRenderer(RendererInterface $renderer): void {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'filtered_entity_bundles' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Render the paragraph output.
    $elements = parent::viewElements($items, $langcode);

    return [
      '#type' => 'processed_text',
      '#text' => $this->renderer->render($elements),
      '#format' => 'stripped_html',
      '#langcode' => $langcode,
    ];
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
