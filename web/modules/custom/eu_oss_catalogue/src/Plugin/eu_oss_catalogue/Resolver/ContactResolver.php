<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Attribute\EuOssCatalogueResolver;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a contact information.
 */
#[EuOssCatalogueResolver(
  id: "contact",
  label: new TranslatableMarkup("Contact")
)]
class ContactResolver extends DefaultResolver implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
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
