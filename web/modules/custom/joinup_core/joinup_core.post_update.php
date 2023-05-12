<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

use Drupal\menu_link_content\Form\MenuLinkContentForm;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Create "Discussions" OG menu item.
 */
function joinup_core_post_update_109300(array &$sandbox): string {
  $db = \Drupal::database();
  /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
  $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
  /** @var \Drupal\Component\Uuid\UuidInterface $uuid_generator */
  $uuid_generator = \Drupal::service('uuid');

  if (!isset($sandbox['data'])) {
    $sql = "SELECT 
              og.og_audience_target_id as group_id, 
              (ml.weight + 1) as weight, 
              ml.menu_name
            FROM {menu_link_content_data} ml
            INNER JOIN {ogmenu_instance__og_audience} og ON SUBSTRING(ml.menu_name, 8) = og.entity_id
            WHERE 
              ml.title = 'Members' AND 
              ml.bundle = 'menu_link_content' AND 
              ml.link__uri like 'route:entity.rdf_entity.member_overview%' ";
    $sandbox['data'] = $db->query($sql)->fetchAll();

    $sandbox['mid'] = (int) $db->query("SELECT MAX(id) FROM {menu_link_content}")
      ->fetchField();
    $sandbox['created'] = \Drupal::time()->getRequestTime();
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);

  $base_table = $db->insert('menu_link_content')
    ->fields(['id', 'revision_id', 'bundle', 'uuid', 'langcode']);
  $base_revision_table = $db->insert('menu_link_content_revision')
    ->fields([
      'id',
      'revision_id',
      'langcode',
      'revision_default',
      'revision_created',
    ]);
  $data_table = $db->insert('menu_link_content_data')
    ->fields([
      'id',
      'revision_id',
      'bundle',
      'langcode',
      'title',
      'menu_name',
      'link__uri',
      'link__options',
      'external',
      'rediscover',
      'weight',
      'expanded',
      'enabled',
      'changed',
      'default_langcode',
      'revision_translation_affected',
    ]);
  $data_revision_table = $db->insert('menu_link_content_field_revision')
    ->fields([
      'id',
      'revision_id',
      'langcode',
      'title',
      'link__uri',
      'link__options',
      'external',
      'enabled',
      'changed',
      'default_langcode',
      'revision_translation_affected',
    ]);


  foreach ($spliced as $data) {
    $sandbox['mid']++;

    $uuid = $uuid_generator->generate();
    $group_id = UriEncoder::encodeUrl($data->group_id);

    $values = [
      'id' => $sandbox['mid'],
      'revision_id' => $sandbox['mid'],
      'langcode' => 'en',
    ];
    $base_table->values($values + [
        'bundle' => 'menu_link_content',
        'uuid' => $uuid,
      ]);
    $base_revision_table->values($values + [
        'revision_default' => 1,
        'revision_created' => $sandbox['created'],
      ]);
    $values += [
      'title' => 'Discussions',
      'link__uri' => "route:view.discussions.overview;rdf_entity=$group_id",
      'link__options' => serialize([]),
      'external' => 0,
      'enabled' => 1,
      'changed' => $sandbox['created'],
      'default_langcode' => 1,
      'revision_translation_affected' => 1,
    ];
    $data_revision_table->values($values);
    $values += [
      'bundle' => 'menu_link_content',
      'menu_name' => $data->menu_name,
      'rediscover' => 0,
      'weight' => $data->weight,
      'expanded' => 0,
    ];
    $data_table->values($values);

    $id = "menu_link_content:$uuid";
    $definition = [
      'menu_name' => $data->menu_name,
      'route_name' => 'view.discussions.overview',
      'route_parameters' => [
        'rdf_entity' => $group_id,
      ],
      'title' => 'Discussions',
      'class' => MenuLinkContent::class,
      'provider' => 'menu_link_content',
      'options' => [],
      'enabled' => 1,
      'weight' => $data->weight,
      'metadata' => [
        'entity_id' => $sandbox['mid'],
      ],
      'form_class' => MenuLinkContentForm::class,
    ];
    $menu_link_manager->addDefinition($id, $definition);

    $sandbox['progress']++;
  }

  $transaction = $db->startTransaction();
  try {
    $base_table->execute();
    $base_revision_table->execute();
    $data_table->execute();
    $data_revision_table->execute();
  }
  catch (\Exception $exception) {
    $transaction->rollBack();
    throw new \Exception($exception->getMessage(), $exception->getCode(), $exception);
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);

  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}
