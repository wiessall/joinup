<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Model\FetchedSolution;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a contact information.
 *
 * @EuOssCatalogueResolver(
 *   id = "contact",
 *   label = "Contact",
 * )
 */
class ContactResolver extends DefaultResolver implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(FieldItemListInterface $fieldItemList, FetchedSolution $fetchedSolution): mixed {
    $storage = $this->entityTypeManager->getStorage('node');
    $nodeType = $this->getConfiguration()['node_type'];

    $targets = [];
    foreach (parent::resolve($fieldItemList, $fetchedSolution) as $contact) {
      $nids = array_values($storage->getQuery()->accessCheck(FALSE)
        ->condition('type', $nodeType)
        ->condition('title', $contact['name'])
        ->execute());
      if ($nids) {
        $targets[] = reset($nids);
        continue;
      }
      $values = ['type' => $nodeType, 'title' => $contact['name']];
      foreach ($contact as $key => $value) {
        if ($key !== 'name') {
          $values["{$nodeType}_$key"] = $value;
        }
      }
      $targets[] = $storage->create($values);
    }

    $fieldItemList->setValue($targets);

    return $targets;
  }

}
