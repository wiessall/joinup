<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
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
  public function resolve(FieldItemListInterface $fieldItemList, EuOssCatalogueFetchedSolution $fetchedSolution): mixed {
    $storage = $this->entityTypeManager->getStorage('node');

    $targets = [];
    foreach (parent::resolve($fieldItemList, $fetchedSolution) as $contact) {
      $nids = array_values($storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'oss_contractor')
        ->condition('title', $contact['name'])
        ->execute());
      if ($nids) {
        $targets[] = reset($nids);
        continue;
      }
      $targets[] = $storage->create([
        'type' => 'oss_contractor',
        'title' => $contact['name'],
        'oss_contractor_until' => $contact['until'] ?? NULL,
        'oss_contractor_email' => $contact['email'] ?? NULL,
        'oss_contractor_website' => $contact['website'] ?? NULL,
      ]);
    }

    $fieldItemList->setValue($targets);

    return $targets;
  }

}
