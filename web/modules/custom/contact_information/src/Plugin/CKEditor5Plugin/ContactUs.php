<?php

declare(strict_types=1);

namespace Drupal\contact_information\Plugin\CKEditor5Plugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Contact us plugin configuration.
 */
class ContactUs extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   The Joinup Group manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected JoinupGroupManagerInterface $groupManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('joinup_group.group_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $group = $this->groupManager->getGroupFromContext();
    if (!$group) {
      return [
        'contactus' => [
          'contactInformation_enabled' => FALSE,
        ],
      ];
    }

    $url = Url::fromRoute('entity.node.contact', ['node' => $group->id()])->toString();

    return [
      'contactus' => [
        'contactInformation_enabled' => !empty($group->getValidatedContactInformationEmails()),
        'contactInformation_url' => $url,
      ],
    ];
  }

}
