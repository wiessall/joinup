<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;

/**
 * Import new landing page.
 */
function joinup_core_deploy_108800(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
  $module_installer = \Drupal::service('module_installer');
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  // Import new content.
  $module_installer->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')->importContent('interoperable_europe', TRUE);
  $module_installer->uninstall(['default_content']);

  // Disable auto-created left-side menu link.
  $landing_page = $entity_repository->loadEntityByUuid('node', '61e83391-9f96-4c9e-995d-9b22705b9f9f');
  $link_plugins = \Drupal::service('plugin.manager.menu.link')->loadLinksByRoute('entity.node.canonical', [
    'node' => $landing_page->id(),
  ], 'ogmenu-3600');
  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $link_plugin */
  $link_plugin = reset($link_plugins);
  $entity_repository
    ->loadEntityByUuid('menu_link_content', $link_plugin->getDerivativeId())
    ->setUnpublished()
    ->save();

  // Remove old IOP landing page.
  \Drupal::entityTypeManager()->getStorage('node')->load(704740)->delete();
}

/**
 * Update path alias IOPE.
 */
function joinup_core_deploy_108801(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity')
    ->load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);

  // Get all entities from IOPE.
  $content = $collection->getGroupContentIds();

  $collection->path = ['alias' => '/interoperable-europe', 'pathauto' => 0];
  $collection->save();

  // Update entities in IOPE group.
  foreach ($content as $type => $entities) {
    foreach ($entities as $ids) {
      foreach ($ids as $id) {
        $entity = \Drupal::entityTypeManager()->getStorage($type)->load($id);
        $entity->path = ['pathauto' => 1];
        $entity->save();
      }
    }
  }
}

/**
 * Update the topic vocabulary terms.
 */
function joinup_core_deploy_108802(array &$sandbox): string {
  if (empty($sandbox['tasks'])) {
    $sandbox['tasks'] = [
      'createTopicTerm' => [
        ['Digital government', NULL],
        ['Energy and environment', NULL],
        ['Energy and environment', 'http://joinup.eu/ontology/topic/category#energy-and-environment'],
        ['Smart cities', 'http://joinup.eu/ontology/category#digital-government'],
      ],
      'moveTopicChildrenToNewParent' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
      ],
      'renameTopic' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'Free and open source software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'ICT standardisation',
        ],
      ],
      'changeTopicId' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
      ],
      'changeTopicParent' => [
        [
          [
            'http://joinup.eu/ontology/topic#business-and-competition',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#free-and-open-source-software',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#industry-and-production',
            'http://joinup.eu/ontology/category#economics'
          ],
          [
            'http://joinup.eu/ontology/topic#content-and-knowledge-management',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#statistics',
            'http://joinup.eu/ontology/topic/category#ict',
          ],
          [
            'http://joinup.eu/ontology/topic#ejustice',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#european-policies',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#law',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#eparticipation-and-evoting',
            'http://joinup.eu/ontology/topic/category#education',
          ],
          [
            'http://joinup.eu/ontology/topic#science-and-technology',
            'http://joinup.eu/ontology/topic/category#education',
          ],
          [
            'http://joinup.eu/ontology/topic#demography-and-population',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#einclusion',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
          [
            'http://joinup.eu/ontology/topic#social',
            'http://joinup.eu/ontology/topic/category#digital-government',
          ],
        ]
      ],
      'updateDbReference' => [
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
      ],
      'createRedirect' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#ict-standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy_and_environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
      ],
      // All terms should be empty in references/children after the migration.
      'deleteTopicTerm' => [
        'http://joinup.eu/ontology/topic/category#egovernment',
        'http://joinup.eu/ontology/topic/category#energy',
        'http://joinup.eu/ontology/topic/category#environment',
        'http://joinup.eu/ontology/topic/category#industry-and-production',
        'http://joinup.eu/ontology/topic/category#information',
        'http://joinup.eu/ontology/topic/category#law',
        'http://joinup.eu/ontology/topic/category#science-and-technology',
        'http://joinup.eu/ontology/topic/category#social',
        'http://joinup.eu/ontology/topic#defence',
        'http://joinup.eu/ontology/topic#economic-analysis',
        'http://joinup.eu/ontology/topic#eu-finance',
        'http://joinup.eu/ontology/topic#open-government',
        'http://joinup.eu/ontology/topic#energy',
        'http://joinup.eu/ontology/topic#environment',
        'http://joinup.eu/ontology/topic#authorisation-and-authentication',
        'http://joinup.eu/ontology/topic#information',
        'http://joinup.eu/ontology/topic#security',
        'http://joinup.eu/ontology/topic#politics',
      ],
    ];
  }

  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $operation = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'], 0, 1);
  foreach ($tasks as $task) {
    switch ($operation) {
      case 'createTopicTerm':
      case 'renameTopic':
      case 'changeTopicId':
      case 'changeTopicParent':
        $vocab_manager->{$operation}($task[0], $task[1]);
        break;

      case 'moveTopicChildrenToNewParent':
      case 'updateDbReference':
      case 'createRedirect':
        $vocab_manager->{$operation}($task['source'], $task['destination']);
        break;

      case 'deleteTopicTerm':
        $vocab_manager->{$operation}($task);
        break;

    }
  }

  if (empty($sandbox['tasks'])) {
    $sandbox['#finished'] = 1;
  }

  return "All topics have been updated";
}
