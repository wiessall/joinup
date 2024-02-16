<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Model\Project;
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
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManger
   *   The language manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManger,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $this->languageManger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(?array &$value, Project $project, array $metadata, FieldItemListInterface $fieldItemList): void {
    parent::resolve($value, $project, $metadata, $fieldItemList);
    if (!$value) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $nodeType = $this->getConfiguration()['node_type'];

    $targets = [];
    foreach ($value as $langcode => $contacts) {
      foreach ($contacts as $contact) {
        if (is_string($contact)) {
          // This is a malformed contact because it should be an array, but we
          // allow this and amend later, when we check the schema.
          $contact = ['name' => $contact];
        }
        if (empty($contact['name'])) {
          // Cannot handle this entry.
          continue;
        }
        $nids = array_values($storage->getQuery()->accessCheck(FALSE)
          ->condition('type', $nodeType)
          ->condition('title', $contact['name'])
          ->execute());

        if ($nids) {
          $targets[$langcode][] = reset($nids);
          continue;
        }

        $contactValues = ['type' => $nodeType, 'title' => $contact['name']];

        foreach ($contact as $key => $contactValue) {
          if ($key !== 'name') {
            $contactValues["{$nodeType}_$key"] = $contactValue;
          }
        }
        $targets[$langcode][] = $storage->create($contactValues);
      }
    }

    $value = $targets;
  }

}
