<?php

declare(strict_types=1);

namespace Drupal\atio\Hook;

use Drupal\atio\AtioDisplaySwitch;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Switches and composes the renderings of an atio_innovation page.
 *
 * @see \Drupal\atio\AtioDisplaySwitch
 */
final class AtioDisplayHooks {

  use StringTranslationTrait;

  /**
   * The pseudo-field that renders the node again in the atio_fields mode.
   *
   * Placed on the default display, it lets "both" be composed from the
   * atio_fields display instead of repeating its layout.
   */
  public const ATIO_FIELDS_PSEUDO_FIELD = 'atio_fields_view';

  public function __construct(
    private readonly AtioDisplaySwitch $displaySwitch,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_node_view_mode_alter().
   */
  #[Hook('node_view_mode_alter')]
  public function nodeViewModeAlter(string &$view_mode, EntityInterface $entity): void {
    $view_mode = $this->displaySwitch->resolveViewMode($entity, $view_mode);
  }

  /**
   * Implements hook_node_build_defaults_alter().
   *
   * The view mode chosen above depends on the query string, but nothing in the
   * entity's own cacheability says so. Without this context, the dynamic page
   * cache serves whichever rendering was built first to every later reader.
   * $view_mode here is the one originally requested, before the switch.
   */
  #[Hook('node_build_defaults_alter')]
  public function nodeBuildDefaultsAlter(array &$build, EntityInterface $entity, string $view_mode): void {
    if ($this->displaySwitch->buildVaries($entity, $view_mode)) {
      $build['#cache']['contexts'][] = AtioDisplaySwitch::CACHE_CONTEXT;
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra['node'][AtioDisplaySwitch::BUNDLE]['display'][self::ATIO_FIELDS_PSEUDO_FIELD] = [
      'label' => $this->t('ATIO fields (rendered)'),
      'description' => $this->t('This innovation rendered in the "ATIO fields" view mode. Lets another display include that layout without repeating it.'),
      'weight' => 0,
      'visible' => FALSE,
    ];
    return $extra;
  }

  /**
   * Implements hook_node_view().
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$display->getComponent(self::ATIO_FIELDS_PSEUDO_FIELD)) {
      return;
    }
    // Rendering the atio_fields mode from inside itself would recurse until
    // the process runs out of memory. Refuse rather than trust the display
    // configuration never to place the pseudo-field there.
    if ($view_mode === 'atio_fields' || $display->getMode() === 'atio_fields') {
      return;
    }
    $build[self::ATIO_FIELDS_PSEUDO_FIELD] = $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($entity, 'atio_fields');
  }

}
