<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\CKEditorPlugin;

use Drupal\Core\Url;
use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Contact us" plugin.
 *
 * @CKEditorPlugin(
 *   id = "contactus",
 *   label = @Translation("Contact us"),
 *   module = "ckeditor_contactus"
 * )
 */
class ContactInformation extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    return \Drupal::service('extension.list.module')->getPath('contact_information') . '/js/plugins/plugin.js';
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
        'image' => \Drupal::service('extension.list.module')->getPath('contact_information') . '/js/plugins/contact.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    $group = \Drupal::getContainer()->get('joinup_group.group_manager')->getGroupFromContext();
    if (!$group) {
      return [
        'contactInformation_disable' => FALSE,
      ];
    }

    $url = Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->setOption('query', [
      'destination' => '',
    ])->toString();
    $login = Url::fromRoute('cas.login')->setOption('query', [
      'destination' => Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->toString(),
    ])->setAbsolute()->toString();
    $disable = !empty($group->getValidatedContactInformation());

    return [
      'contactInformation_disable' => $disable,
      'contactInformation_url' => $url,
      'contactInformation_login' => $login,
    ];
  }

}
