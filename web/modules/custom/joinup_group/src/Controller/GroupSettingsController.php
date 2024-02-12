<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for collection/solution settings.
 */
class GroupSettingsController extends ControllerBase {

  /**
   * The OG access service.
   */
  protected OgAccessInterface $ogAccess;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(OgAccessInterface $og_access) {
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('og.access')
    );
  }

  /**
   * Provides a controller for the 'joinup_group.settings_form' route title.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The content as a render array.
   */
  public function title(GroupInterface $rdf_entity): MarkupInterface {
    return $this->t('@label @type settings', [
      '@label' => $rdf_entity->label(),
      '@type' => $rdf_entity->get('rid')->entity->getSingularLabel(),
    ]);
  }

  /**
   * Provides a controller for the 'joinup_group.settings_form' route.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return array
   *   The content as a render array.
   */
  public function settings(GroupInterface $rdf_entity): array {
    $meta_entity = MetaEntity::loadOrCreate($this->getMetaEntityBundle($rdf_entity), $rdf_entity);
    $form_state_additions = [
      'redirect' => Url::fromRoute('entity.rdf_entity.canonical', [
        'rdf_entity' => $rdf_entity->id(),
      ]),
    ];
    return $this->entityFormBuilder()->getForm($meta_entity, 'default', $form_state_additions);
  }

  /**
   * Provides an access controller for the 'joinup_group.settings_form' route.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(GroupInterface $rdf_entity): AccessResultInterface {
    $meta_entity = MetaEntity::loadOrCreate($this->getMetaEntityBundle($rdf_entity), $rdf_entity);
    $access_result = $meta_entity->access('update', NULL, TRUE);
    if ($access_result->isAllowed()) {
      return $access_result;
    }
    return $access_result->orIf($this->ogAccess->userAccess($rdf_entity, 'update ' . $this->getMetaEntityBundle($rdf_entity) . ' meta-entity'));
  }

  /**
   * Format a meta entity bundle ID.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $rdf_entity
   *   The group where to show settings.
   *
   * @return string
   *   The meta entity bundle ID.
   */
  protected function getMetaEntityBundle(GroupInterface $rdf_entity): string {
    return "{$rdf_entity->bundle()}_settings";
  }

}
