<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\CKEditorPlugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "Contact us" plugin.
 *
 * @CKEditorPlugin(
 *   id = "contactus",
 *   label = @Translation("Contact us"),
 *   module = "ckeditor_contactus"
 * )
 */
class ContactInformation extends CKEditorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $group_manager
   *   The Joinup Group manager service.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, protected JoinupGroupManagerInterface $group_manager) {
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
  public function getFile(): string {
    return $this->getModulePath('contact_information') . '/js/plugins/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons(): array {
    return [
      'contactus' => [
        'label' => $this->t('Contact us'),
        'image' => $this->getModulePath('contact_information') . '/js/plugins/contact.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    $group = $this->group_manager->getGroupFromContext();
    if (!$group) {
      return [
        'contactInformation_disable' => FALSE,
      ];
    }

    $url = Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->toString();
    $disable = !empty($group->getValidatedContactInformation());

    return [
      'contactInformation_disable' => $disable,
      'contactInformation_url' => $url,
    ];
  }

}
