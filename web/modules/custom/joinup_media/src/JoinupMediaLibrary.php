<?php

declare(strict_types=1);

namespace Drupal\joinup_media;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5Plugin\MediaLibrary;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\editor\EditorInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\media_library\MediaLibraryState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replacement for core's MediaLibrary plugin class.
 *
 * Overriding the original class in order to inject also the group ID in the
 * Media Library state, passed to the dialog as query string.
 *
 * @see \joinup_media_ckeditor5_plugin_info_alter()
 * @see \Drupal\ckeditor5\Plugin\CKEditor5Plugin\MediaLibrary()
 */
class JoinupMediaLibrary extends MediaLibrary {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   The Joinup group manager service.
   */
  public function __construct(array $configuration, string $pluginId, CKEditor5PluginDefinition $pluginDefinition, EntityTypeManagerInterface $entityTypeManager, protected JoinupGroupManagerInterface $groupManager) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $entityTypeManager);
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
      $container->get('joinup_group.group_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $dynamic_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);

    if (!$group = $this->groupManager->getGroupFromContext()) {
      // Only act in the context of a group.
      return $dynamic_plugin_config;
    }

    // Reverse engineering: Restore the MediaLibraryState object.
    $query_string = parse_url($dynamic_plugin_config['drupalMedia']['libraryURL'], PHP_URL_QUERY);
    parse_str($query_string, $query);
    /** @var string $media_library_opener_id */
    /** @var string[] $media_library_allowed_types */
    /** @var string $media_library_selected_type */
    /** @var int $media_library_remaining */
    /** @var array $media_library_opener_parameters */
    extract($query);

    // Add the group ID to be passed to the media library.
    $media_library_opener_parameters['group_id'] = $group->id();

    $state = MediaLibraryState::create($media_library_opener_id, $media_library_allowed_types, $media_library_selected_type, $media_library_remaining, $media_library_opener_parameters);
    $dynamic_plugin_config['drupalMedia']['libraryURL'] = Url::fromRoute('media_library.ui')
      ->setOption('query', $state->all())
      ->toString(TRUE)
      ->getGeneratedUrl();

    return $dynamic_plugin_config;
  }

}
