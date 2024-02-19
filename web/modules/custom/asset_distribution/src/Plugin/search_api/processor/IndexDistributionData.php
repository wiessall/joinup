<?php

declare(strict_types=1);

namespace Drupal\asset_distribution\Plugin\search_api\processor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes the distribution data into the parent solution or release fields.
 *
 * Distributions are not shown in the site-wide search results. Instead, their
 * data (title, description, URL, licence, etc) is indexed in the parent Search
 * API record, so that searching for a keyword belonging to a distribution will
 * retrieve their parent (release or solution).
 *
 * @SearchApiProcessor(
 *   id = "index_distribution_data",
 *   label = @Translation("Index distribution data"),
 *   description = @Translation("Indexes the distribution data into the distribution parent Search API record."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   },
 * )
 */
class IndexDistributionData extends ProcessorPluginBase {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, protected FileUrlGeneratorInterface $fileUrlGenerator) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'rdf_entity') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      // Append data from child distributions.
      $this->appendDistributionData($item);
    }
  }

  /**
   * Appends the distribution data to the parent's Search API description field.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The Search API item.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when $item->getOriginalObject() parameter, $load, is TRUE but the
   *   object could not be loaded.
   */
  protected function appendDistributionData(ItemInterface $item): void {
    /** @var \Drupal\rdf_entity\RdfInterface $entity */
    $entity = $item->getOriginalObject()->getValue();

    // Only solutions and releases should receive distribution data.
    if (!in_array($entity->bundle(), ['asset_release', 'solution'])) {
      return;
    }

    $distributionFieldName = $entity->bundle() === 'asset_release' ? 'field_isr_distribution' : 'field_is_distribution';
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $distributionReference */
    $distributionReference = $entity->get($distributionFieldName);
    if ($distributionReference->isEmpty()) {
      return;
    }

    // Get the parent Search API field.
    $sapiFieldName = $entity->bundle() === 'asset_release' ? 'field_isr_description' : 'field_is_description';
    $sapiField = $item->getField($sapiFieldName);

    // Iterate over all distributions and append their data to Search API field.
    /** @var \Drupal\rdf_entity\RdfInterface $distribution */
    foreach ($distributionReference->referencedEntities() as $distribution) {
      // Add the distribution title.
      $sapiField->addValue($distribution->label());

      // Add the distribution description.
      $distributionFieldItemList = $distribution->get('field_ad_description');
      if (!$distributionFieldItemList->isEmpty()) {
        $sapiField->addValue(check_markup($distributionFieldItemList->value, $distributionFieldItemList->format));
      }

      // Add the distribution URL.
      /** @var \Drupal\file_url\Plugin\Field\FieldType\FileUrlFieldItemList $distributionFieldItemList */
      $distributionFieldItemList = $distribution->get('field_ad_access_url');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $file) {
          $file_url = $file->getFileUri();
          if (UrlHelper::isExternal($file_url) && !UrlHelper::externalIsLocal($file_url, $GLOBALS['base_url'])) {
            $url = $file_url;
          }
          else {
            $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          }
          $sapiField->addValue($url);
        }
      }

      // Add the distribution licence.
      $distributionFieldItemList = $distribution->get('field_ad_licence');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $licence) {
          $sapiField->addValue($licence->label());
        }
      }

      // Add the distribution format.
      $distributionFieldItemList = $distribution->get('field_ad_format');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $format) {
          $sapiField->addValue($format->label());
        }
      }

      // Add the distribution representation technique.
      $distributionFieldItemList = $distribution->get('field_ad_repr_technique');
      if (!$distributionFieldItemList->isEmpty()) {
        foreach ($distributionFieldItemList->referencedEntities() as $technique) {
          $sapiField->addValue($technique->label());
        }
      }
    }
  }

}
