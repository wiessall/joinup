<?php

declare(strict_types = 1);

namespace Drupal\meta_entity\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form class for 'meta_entity_type' add/edit.
 */
class MetaEntityTypeForm extends BundleEntityFormBase {

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $bundle_info
   *   The bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeBundleInfo $bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->bundleInfo = $bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    if ($this->operation === 'add') {
      $form['#title'] = $this->t('Add meta entity type');
    }
    else {
      $form['#title'] = $this->t('Edit %label meta entity type', [
        '%label' => $this->getEntity()->label(),
      ]);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $this->getEntity()->label(),
      '#description' => $this->t('The human-readable name of this meta entity type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->getEntity()->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => ['Drupal\meta_entity\Entity\RdfMetaEntityType', 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this meta entity type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $this->getEntity()->getDescription(),
    ];
    $this->buildEntityTypeOptions($form);

    return $this->protectBundleIdElement($form);
  }

  /**
   * Builds the mapping elements.
   *
   * @param array $form
   *   The Form API form render array.
   */
  protected function buildEntityTypeOptions(array &$form): void {
    $form['map_help'] = [
      [
        '#markup' => $this->t('Select the bundles that are qualified to link metadata. Optionally, you can enter a reverse reference field name. If such a field name is specified the target entity bundle will expose a computed entity reference field with this name linking back to the meta entity.'),
      ],
    ];

    $mapping = $this->getEntity()->get('mapping');
    $form['mapping'] = [];

    $entity_type_definitions = $this->entityTypeManager->getDefinitions();
    foreach ($this->bundleInfo->getAllBundleInfo() as $entity_type_id => $bundles_info) {
      $entity_type = $entity_type_definitions[$entity_type_id];
      if (!$entity_type instanceof ContentEntityType || !$entity_type->entityClassImplements(FieldableEntityInterface::class) || $entity_type_id === $this->getEntity()->getEntityType()->getBundleOf()) {
        continue;
      }

      $form['mapping'][$entity_type_id]['entity_type_id'] = [
        '#type' => 'checkbox',
        '#title' => '<strong>' . $entity_type->getLabel() . '</strong>',
        '#default_value' => array_key_exists($entity_type_id, $mapping),
      ];

      $has_bundle = $entity_type->hasKey('bundle') && $entity_type->getBundleEntityType();

      $form['mapping'][$entity_type_id]['bundles'] = [
        '#type' => $has_bundle ? 'fieldset' : 'container',
        '#open' => TRUE,
        '#title' => $this->t('@type bundles', [
          '@type' => $entity_type->getLabel(),
        ]),
        '#states' => [
          'visible' => [
            'input[name="mapping[' . $entity_type_id . '][entity_type_id]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      foreach ($bundles_info as $bundle_id => $bundle_info) {
        if ($has_bundle) {
          $form['mapping'][$entity_type_id]['bundles'][$bundle_id]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $bundle_info['label'],
            '#default_value' => isset($mapping[$entity_type_id]) && array_key_exists($bundle_id, $mapping[$entity_type_id]),
          ];
        }
        else {
          // Set as NULL so that we can catch this case later, in validation.
          $form['mapping'][$entity_type_id]['bundles'][$bundle_id]['enabled'] = [
            '#type' => 'value',
            '#value' => NULL,
          ];
        }
        $form['mapping'][$entity_type_id]['bundles'][$bundle_id]['settings'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Settings for @bundle @entity_type', [
            '@bundle' => $bundle_info['label'],
            '@entity_type' => $entity_type->getSingularLabel(),
          ]),
          '#states' => [
            'visible' => [
              'input[name="mapping[' . $entity_type_id . '][bundles][' . $bundle_id . '][enabled]"]' => [
                'checked' => TRUE,
              ],
            ],
          ],
        ];

        $form['mapping'][$entity_type_id]['bundles'][$bundle_id]['settings']['field_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Reverse reference field name'),
          '#default_value' => $mapping[$entity_type_id][$bundle_id]['field_name'] ?? NULL,
          '#maxlength' => 32,
        ];
        $form['mapping'][$entity_type_id]['bundles'][$bundle_id]['settings']['auto_create'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Auto-create a meta entity when a new @bundle @entity_type is created', [
            '@bundle' => $bundle_info['label'],
            '@entity_type' => $entity_type->getSingularLabel(),
          ]),
          '#default_value' => $mapping[$entity_type_id][$bundle_id]['auto_create'] ?? FALSE,
        ];
      }
    }

    // Sort by entity type label.
    uasort($form['mapping'], function (array $a, array $b): int {
      $a_title = (string) $a['entity_type_id']['#title'] ?? '';
      $b_title = (string) $b['entity_type_id']['#title'] ?? '';
      return strnatcasecmp($a_title, $b_title);
    });

    $form['mapping']['#tree'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    $actions['delete']['#value'] = $this->t('Delete');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Massage form values structure.
    $mapping = [];
    $storage = $this->entityTypeManager->getStorage('meta_entity_type');
    foreach ($form_state->getValue('mapping') as $entity_type_id => $selection) {
      if (!$selection['entity_type_id']) {
        continue;
      }

      foreach ($selection['bundles'] as $bundle_id => $info) {
        // Entity types without bundles have the 'enabled' info strictly set to
        // NULL in ::buildEntityTypeOptions().
        // @see self::buildEntityTypeOptions()
        if ($info['enabled'] || ($info['enabled'] === NULL)) {
          if ($field_name = trim($info['settings']['field_name']) ?? NULL) {
            // Validate the field name length and allowed characters.
            if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/i', $field_name)) {
              $form_state->setErrorByName("mapping][{$entity_type_id}][bundles][{$bundle_id}][settings][field_name", $this->t('The field name should start with a lowercase latin letter and may contain only lowercase letters, digits and the underscore character.'));
            }
            // Validate that this field name is unique within the same target
            // entity type and meta entity type.
            $ids = $storage->getQuery()
              ->condition("mapping.{$entity_type_id}.*.field_name", $field_name)
              ->condition('id', $this->getEntity()->id(), '<>')
              ->execute();
            if ($ids) {
              $id = reset($ids);
              $meta_entity_type = $storage->load($id);
              $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
              $form_state->setErrorByName("mapping][{$entity_type_id}][bundles][{$bundle_id}][field_name", $this->t('The field name is already use in %meta_entity_type for the same %entity_type entity type.', [
                '%meta_entity_type' => $meta_entity_type->label(),
                '%entity_type' => $entity_type->getLabel(),
              ]));
            }

            // Validate that this field name is not already in use.
            if ($existing_definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id)[$field_name] ?? NULL) {
              if ($existing_definition->getFieldStorageDefinition()->getProvider() !== 'meta_entity') {
                $form_state->setErrorByName("mapping][{$entity_type_id}][bundles][{$bundle_id}][field_name", $this->t('A field with this name already exists.'));
              }
            }
          }
          $mapping[$entity_type_id][$bundle_id] = [
            'field_name' => $field_name,
            'auto_create' => (bool) $info['settings']['auto_create'],
          ];
        }
      }
    }
    $form_state->setValue('mapping', $mapping);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);
    $args = ['%name' => $this->getEntity()->label()];
    if ($status === SAVED_UPDATED) {
      $message = $this->t('The meta entity type %name has been updated.', $args);
    }
    elseif ($status === SAVED_NEW) {
      $message = $this->t('The meta entity type %name has been added.', $args);
    }
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->getEntity()->toUrl('collection'));

    return $status;
  }

}
