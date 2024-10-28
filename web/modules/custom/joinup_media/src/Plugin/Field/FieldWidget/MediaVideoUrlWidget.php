<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSimple;
use Drupal\media\MediaInterface;
use Drupal\og\OgContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Media video URL widget.
 *
 * This widget is primarily intended for use in video paragraphs.
 * Using layout paragraphs with the Media Library widget would result in nested
 * modals, which is to be avoided.
 */
#[FieldWidget(
  id: 'joinup_media_video_url',
  label: new TranslatableMarkup('Interoperable Europe Portal Media video URL'),
  field_types: [
    'entity_reference',
  ],
)]
class MediaVideoUrlWidget extends InlineEntityFormSimple {

  /**
   * The OG context service.
   *
   * @var \Drupal\og\OgContextInterface
   */
  protected OgContextInterface $ogContext;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, OgContextInterface $og_context) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_bundle_info, $entity_type_manager, $entity_display_repository);
    $this->ogContext = $og_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('og.context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['form_mode'] = 'media_video_url';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // Don't expose IEF settings, we use fixed ones.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    // Only support entity reference fields to media, remote_video bundle.
    if ($field_definition->getSetting('target_type') !== 'media') {
      return FALSE;
    }

    $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'] ?? [];
    return $target_bundles && count($target_bundles) === 1 && isset($target_bundles['remote_video']);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $values = parent::massageFormValues($values, $form, $form_state);

    foreach ($values as $delta => $value) {
      $entity = $value['entity'];
      \assert($entity instanceof MediaInterface);

      // For new entities, if one with same URL and OG context exist, use it.
      // Otherwise, set the OG group.
      if ($entity->isNew()) {
        $url = $entity->get('media_oembed_video')->getString();
        $existing = $this->lookupEntity($url);
        if ($existing) {
          $values[$delta]['entity'] = $existing;
        }
        else {
          $entity->set('og_audience', $this->ogContext->getGroup());
        }
      }
    }

    return $values;
  }

  /**
   * Lookup for an existing remote_video entity.
   */
  protected function lookupEntity(string $url): ?MediaInterface {
    $existing_entity = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'bundle' => 'remote_video',
      'media_oembed_video' => $url,
      'og_audience' => $this->ogContext->getGroup()?->id(),
    ]);
    return $existing_entity ? reset($existing_entity) : NULL;
  }

}
