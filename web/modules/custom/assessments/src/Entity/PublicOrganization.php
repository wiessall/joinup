<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\PublicOrganizationInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the publicorganization entity class.
 *
 * @ContentEntityType(
 *   id = "assessment_organization",
 *   label = @Translation("PublicOrganization"),
 *   label_collection = @Translation("PublicOrganizations"),
 *   label_singular = @Translation("publicorganization"),
 *   label_plural = @Translation("publicorganizations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count publicorganizations",
 *     plural = "@count publicorganizations",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\PublicOrganizationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\PublicOrganizationForm",
 *       "edit" = "Drupal\assessments\Form\PublicOrganizationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessment_organization",
 *   data_table = "assessment_organization_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessment_organization",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment-organization",
 *     "add-form" = "/assessments/public-organization/add",
 *     "canonical" = "/assessments/public-organization/{assessment_organization}",
 *     "edit-form" = "/assessments/public-organization/{assessment_organization}/edit",
 *     "delete-form" = "/assessments/public-organization/{assessment_organization}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment-organization/delete-multiple",
 *   },
 * )
 */
final class PublicOrganization extends AssessmentContent {

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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
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
      ->setDescription(t('The time that the publicorganization was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the publicorganization was last edited.'));

    $fields['classification'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Classification'))
      ->setDescription(t('The classification of the public organization.'))
      // @todo This is a Code field. Convert to vocabulary reference.
      ->setSettings([
        'allowed_values' => [
          'government' => 'Government',
          'non_profit' => 'Non-Profit',
          'private_sector' => 'Private Sector',
          'other' => 'Other',
        ],
      ])
      ->setDefaultValue('other')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Provider fields moved from Assessment Provider widget into
    // Public Organization entity.
    $fields['provider_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Type of public sector body'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'local_public_sector_body' => t('Local public sector body'),
          'regional_public_sector_body' => t('Regional public sector body'),
          'national_public_sector_body' => t('National public sector body'),
          'eu_institution' => t('European Union institution'),
          'eu_agency' => t('European Union agency'),
          'eu_body' => t('European Union body'),
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['provider_country'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Country'))
      ->setDescription(t('Country code (2-letter)'))
      ->setSettings(['max_length' => 2])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['provider_value'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Organization text/value'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
