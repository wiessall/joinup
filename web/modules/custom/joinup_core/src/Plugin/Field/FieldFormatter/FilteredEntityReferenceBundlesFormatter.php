<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Drupal\smart_trim\TruncateHTML;
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
   *   The renderer service.
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
      'trim_length' => 200,
      'trim_chars' => '...',
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

    $elements['trim_length'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trim length'),
      '#default_value' => $this->getSetting('trim_length'),
      '#required' => TRUE,
    ];

    $elements['trim_chars'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trim characters'),
      '#description' => $this->t('Characters to add to the end of the trimmed text if it was longer than the specified trim length'),
      '#default_value' => $this->getSetting('trim_chars'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Render the paragraph output.
    $elements = parent::viewElements($items, $langcode);

    $rendered = [];
    foreach ($elements as $element) {
      $output = (string) $this->renderer->renderPlain($element);
      $output = trim(strip_tags($output));
      // Apply a fix for the CKEditor converting HTML entities. This is
      // because we are prematurely rendering the paragraphs as plain text in
      // order to merge them with the rest of the paragraphs and create a
      // single text that we can later trim and use as a whole.
      $output = html_entity_decode($output);

      $rendered[] = $output;
    }
    $rendered = array_filter($rendered);
    $text = implode(' ', $rendered);

    // Replace multiple whitespaces with a single one. The mb_ereg_replace()
    // function is used because it supports multibyte characters and there are
    // cases where the \n character might be interpreted as a non-breaking
    // space which is not stripped by the preg_replace() function. Example:
    // @codingStandardsIgnoreStart
    //
    //
    //
    //
    //			Upcoming data spaces...
    // @codingStandardsIgnoreEnd
    // The above was generated by stripping down paragraphs.
    $text = mb_ereg_replace('\s{2,}', ' ', $text);

    $truncate = new TruncateHTML();
    return [
      // The reason for typecasting is because layout builder configuration form
      // returns a string even though the schema is set to integer and the
      // value stored is an integer.
      '#markup' => $truncate->truncateChars(
        $text,
        (int) $this->getSetting('trim_length'),
        $this->getSetting('trim_chars'),
      ),
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
