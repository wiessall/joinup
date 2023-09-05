<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_core\Plugin\Field\FieldFormatter\FormatterWithFallbackField;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Layout Builder render subscriber for field blocks.
 *
 * This class is responsible for doing the necessary workaround for Layout
 * Builder's FieldBlock plugins which are configured to use the
 * "joinup_oss_catalogue_fallback" field formatter. This field formatter renders
 * a fallback field with the configured formatter if the original field is
 * empty.
 *
 * Unfortunately, the approach what the field formatter uses does not work for
 * Layout Builder, because when the FieldBlock plugin checks block access, it
 * also involves checking field item list emptiness - and in case of empty
 * fields, the access check returns AccessResultForbidden. We have (and need)
 * this event subscriber to work around that case.
 *
 * @see \Drupal\layout_builder\Plugin\Block\FieldBlock::blockAccess()
 * @see \Drupal\joinup_core\Plugin\Field\FieldFormatter\FormatterWithFallbackField::viewElements()
 */
class LayoutBuilderBuildSubscriber implements EventSubscriberInterface {

  /**
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user service.
   */
  public function __construct(
    protected AccountInterface $currentUser
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // We need higher priority than
    // \Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 50];
    return $events;
  }

  /**
   * Applies the needed workaround for field blocks using fallback formatter.
   *
   * Switches the rendered field and formatter settings for FieldBlock plugin
   * instances using "joinup_oss_catalogue_fallback" formatter if the original
   * block is not accessible by the current user.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof FieldBlock) {
      return;
    }

    // Only check access if the component is not being previewed.
    if ($event->inPreview()) {
      return;
    }

    // If current user has access to the actual block plugin instance, no need
    // to do anything.
    if ($block->access($this->currentUser)) {
      return;
    }

    $blockConfig = $block->getConfiguration();
    $fieldFormatterConfig = $blockConfig['formatter'] ?? [];

    // If the formatter ID config isn't set to "joinup_oss_catalogue_fallback",
    // we don't touch the block instance.
    if (
      empty($fieldFormatterConfig['type']) ||
      $fieldFormatterConfig['type'] !== 'joinup_core_formatter_fallback_field'
    ) {
      return;
    }

    // Do we miss a mandatory config?
    if (empty($fallbackField = $fieldFormatterConfig['settings'][FormatterWithFallbackField::FALLBACK_FIELD_ID_KEY])) {
      return;
    }

    $blockReflection = new \ReflectionClass($block);
    $fieldName = $blockReflection->getProperty('fieldName');
    $fieldName->setValue($block, $fallbackField);

    $blockFallbackConfig = $blockConfig;
    $blockFallbackConfig['formatter']['type'] = $fieldFormatterConfig['settings'][FormatterWithFallbackField::FALLBACK_FIELD_FORMATTER_ID_KEY];
    $blockFallbackConfig['formatter']['settings'] = $fieldFormatterConfig['settings'][FormatterWithFallbackField::FALLBACK_FIELD_FORMATTER_SETTINGS_KEY] ?? [];
    $block->setConfiguration($blockFallbackConfig);
  }

}
