<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Plugin\CKEditor5Plugin;

use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\editor\EditorInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_licence\Entity\LicenceInterface;
use Drupal\solution\Entity\SolutionInterface;

/**
 * CKEditor 5 Contact us plugin configuration.
 */
class Contactus extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $group = \Drupal::routeMatch()->getParameter('rdf_entity');
    if (!$group) {
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof CommunityContentInterface) {
        $group = $node->getGroup();
      }
    }

    $login = $url = NULL;
    $disable = FALSE;
    if ($group && (!$group instanceof CollectionInterface || !$group instanceof SolutionInterface)) {
      $group = !$group instanceof LicenceInterface && $group->hasGroup() ? $group->getGroup() : NULL;
    }

    if ($group) {
      $url = Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->setOption('query', [
        'destination' => '',
      ])->toString();
      $login = Url::fromRoute('cas.login')->setOption('query', [
        'destination' => Url::fromRoute('entity.rdf_entity.contact', ['rdf_entity' => $group->id()])->toString(),
      ])->setAbsolute()->toString();
      $disable = !empty($group->getValidatedContactInformation());
    }

    $config = $static_plugin_config;
    $config += [
      'contactus' => [
        'contactInformation_disable' => $disable,
        'contactInformation_url' => $url,
        'contactInformation_login' => $login,
      ],
    ];
    return $config;
  }

}
