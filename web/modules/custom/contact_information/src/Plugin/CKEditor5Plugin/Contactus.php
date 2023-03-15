<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\CKEditor5Plugin;

use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\editor\EditorInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\solution\Entity\SolutionInterface;

/**
 * CKEditor 5 Contact us plugin configuration.
 */
class Contactus extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $contact_info = $this->getContactInformation(FALSE, NULL);
    $entity = \Drupal::routeMatch()->getParameter('rdf_entity');
    if (!$entity) {
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node) {
        try {
          $group = $node->getGroup();
          $contact_info = $this->getContactInformation(!empty($group->getValidatedContactInformation()), $group);
        }
        catch (MissingGroupException $e) {
        }
      }
    }
    else {
      try {
        $group = !$entity instanceof CollectionInterface && !$entity instanceof SolutionInterface ? $entity->getGroup() : $entity;
        $contact_info = $this->getContactInformation(!empty($group->getValidatedContactInformation()), $group);
      }
      catch (MissingGroupException $e) {
      }
    }

    $config  = $static_plugin_config;
    $config += ['contactus' => $contact_info];
    return $config;

  }

  /**
   * @param bool $disable
   *   Check if exists a valid contact email.
   * @param \Drupal\joinup_group\Entity\GroupInterface|null $group
   *   The group for which the contact us page is being determined.
   *
   * @return array
   */
  private function getContactInformation(bool $disable, ?GroupInterface $group): array {
    return [
      'contactInformation_disable' => $disable,
      'contactInformation_url' => $disable ? Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->setOption('query', ['destination' => ''])->toString() : NULL,
      'contactInformation_login' => $disable ? Url::fromRoute('cas.login')->setOption('query', ['destination' => Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->toString()])->setAbsolute()->toString() : NULL,
    ];

  }

}
