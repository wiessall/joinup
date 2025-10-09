<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\AssetInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the asset entity class.
 *
 * @ContentEntityType(
 *   id = "assessments_asset",
 *   label = @Translation("Asset"),
 *   label_collection = @Translation("Assets"),
 *   label_singular = @Translation("asset"),
 *   label_plural = @Translation("assets"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assets",
 *     plural = "@count assets",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\AssetListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssetForm",
 *       "edit" = "Drupal\assessments\Form\AssetForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessments_asset",
 *   data_table = "assessments_asset_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessments_asset",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/asset",
 *     "add-form" = "/assessments/asset/add",
 *     "canonical" = "/assessments/asset/{assessments_asset}",
 *     "edit-form" = "/assessments/asset/{assessments_asset}/edit",
 *     "delete-form" = "/assessments/asset/{assessments_asset}/delete",
 *     "delete-multiple-form" = "/admin/content/asset/delete-multiple",
 *   },
 * )
 */
final class Asset extends AssessmentContent {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the asset was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the asset was last edited.'));

    $fields['identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Identifier'))
      ->setDescription(t('A unique identifier for the asset (e.g. URI, registry ID, internal ID).'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Renamed to asset_type to avoid conflict with 'type' which is a commonly
    // used field name.
    $fields['asset_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Asset type'))
      ->setDescription(t('The type of the asset (e.g. Software, Dataset, Specification, Service).'))
      // @todo Use a proper taxonomy term reference if more types are needed.
      ->setSettings([
        'allowed_values' => [
          'software' => t('Software'),
          'dataset' => t('Dataset'),
          'specification' => t('Specification'),
          'service' => t('Service'),
          'other' => t('Other'),
        ],
      ])
      ->setDefaultValue('software')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Renamed to asset_uri to avoid conflict with 'uri' which is a commonly
    // used field name in RDF Sync.
    $fields['asset_uri'] = BaseFieldDefinition::create('link')
      ->setLabel(t('URI'))
      ->setDescription(t('A URI or homepage referencing the asset.'))
      ->setSettings([
        'link_type' => 18, // external only
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // @todo Create the documented_in field as an entity reference to a media
    //   entity of type document once that is available.
    return $fields;
  }

}
