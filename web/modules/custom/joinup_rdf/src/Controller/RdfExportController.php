<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rdf_sync\Model\RdfSyncFormat;
use Drupal\rdf_sync\RdfSyncMapper;
use EasyRdf\Format;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for the RDF export.
 */
class RdfExportController extends ControllerBase {

  /**
   * The RDF Sync mapper service.
   */
  protected RdfSyncMapper $rdfSyncMapper;

  /**
   * Instantiates a new RdfExportController object.
   */
  public function __construct(RdfSyncMapper $mapper) {
    $this->rdfSyncMapper = $mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * Checks that the RDF export is accessible.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\Core\Entity\EntityAccessCheck
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    $entity = $this->getEntity($route_match);
    $is_mapped = $this->rdfSyncMapper->isMappedEntity($entity);

    return AccessResult::allowedIf($is_mapped)
      ->andIf($entity->access('view', $account, TRUE));
  }

  /**
   * Provides the title callback.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The title for the RDF export route.
   */
  public function title(RouteMatchInterface $route_match): MarkupInterface {
    $entity = $this->getEntity($route_match);
    return $this->t('Download the %label @type as:', [
      '%label' => $entity->label(),
      '@type' => $entity->getEntityType()->getSingularLabel(),
    ]);
  }

  /**
   * Build a list of possible download links to RDF serialization methods.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The RouteMatch object.
   *
   * @return array
   *   Render array with list of download links.
   */
  public function downloadLinks(RouteMatchInterface $route_match): array {
    $entity = $this->getEntity($route_match);
    $list = ['#theme' => 'item_list'];
    foreach ($this->getSupportedFormats() as $format) {
      $list['#items'][] = [
        '#type' => 'link',
        '#title' => $format->getLabel(),
        '#url' => $entity->toUrl('canonical', ['query' => ['_format' => $format->getName()]]),
      ];
    }

    return [$list];
  }

  /**
   * Returns the supported RDF export formats.
   *
   * @return \EasyRdf\Format[]
   *   The supported formats.
   */
  protected function getSupportedFormats(): array {
    $formats = [];
    foreach (RdfSyncFormat::cases() as $format) {
      $formats[$format->value] = Format::getFormat($format->value);
    }
    return $formats;
  }

  /**
   * Returns the entity this route is for.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   returns the Entity in question.
   *
   * @throws \Drupal\Core\Access\AccessException
   *   An AccessException is thrown if the entity couldn't be loaded.
   */
  protected function getEntity(RouteMatchInterface $route_match) {
    $entity_type = $route_match->getRouteObject()->getOption('_joinup_rdf_export_entity_type');
    $entity = $route_match->getParameter($entity_type);
    if ($entity instanceof EntityInterface) {
      return $entity;
    }

    throw new AccessException(sprintf('%s is not a valid entity route.', $route_match->getRouteName()));
  }

}
