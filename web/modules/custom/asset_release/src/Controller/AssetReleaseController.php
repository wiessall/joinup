<?php

declare(strict_types=1);

namespace Drupal\asset_release\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for asset release forms.
 *
 * Handles the form to perform actions when it is called by a route that
 * includes an rdf_entity id.
 */
class AssetReleaseController extends ControllerBase {

  /**
   * Controller for the base form.
   *
   * We need to override the functionality of the create form for pages
   * that include the rdf_entity id in the url so that the og audience field
   * is auto completed.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection rdf_entity.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function add(RdfInterface $rdf_entity): array {
    return $this->entityFormBuilder()->getForm($this->createNewAssetRelease($rdf_entity));
  }

  /**
   * Handles access to the asset_release add form through solution pages.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The RDF entity for which the custom page is created.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The RDF entity for which the custom page is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createAssetReleaseAccess(RdfInterface $rdf_entity, ?AccountInterface $account = NULL): AccessResultInterface {
    if ($rdf_entity->bundle() !== 'solution') {
      throw new NotFoundHttpException();
    }

    return $this->entityTypeManager()->getAccessControlHandler('rdf_entity')->createAccess('asset_release', $account, [
      // The group will be consumed in joinup_rdf_entity_create_access().
      'group' => $rdf_entity,
    ], TRUE);
  }

  /**
   * Returns a build array for the solution releases overview page.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution rdf entity.
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   *   The build array for the page or a 404 response.
   */
  public function overview(RdfInterface $rdf_entity) {
    /** @var \Drupal\solution\SolutionReleasesAndDistributionsFieldItemList $field */
    $field = $rdf_entity->get('releases_and_distributions');
    if ($field->isEmpty()) {
      return (new TrustedRedirectResponse('/not-found'))
        ->setStatusCode(404)
        ->addCacheableDependency($rdf_entity);
    }
    $entities = $field->referencedEntities();

    // Sort entities by date in reverse.
    usort($entities, static function (ContentEntityInterface $a, ContentEntityInterface $b) {
      return $b->getCreatedTime() - $a->getCreatedTime();
    });

    // Put a flag on the standalone distributions so they can be identified for
    // theming purposes.
    foreach ($entities as $entity) {
      if ($entity->bundle() === 'distribution') {
        $entity->standalone = TRUE;
      }
    }

    // Mark the first release as the latest.
    // @see asset_release_preprocess_rdf_entity()
    foreach ($entities as $entity) {
      if ($entity->bundle() === 'asset_release') {
        $entity->is_latest_release = TRUE;
        break;
      }
    }

    $build = [
      [
        '#theme' => 'asset_release_releases_download',
        '#releases' => $entities,
      ],
    ];

    (new CacheableMetadata())
      ->addCacheableDependency($rdf_entity)
      ->applyTo($build);

    return $build;
  }

  /**
   * Page title callback for the solution releases overview.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution rdf entity.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The page title.
   */
  public function overviewPageTitle(RdfInterface $rdf_entity): MarkupInterface {
    return $this->t('Releases for %solution solution', ['%solution' => $rdf_entity->label()]);
  }

  /**
   * Access callback for the solution releases overview.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution rdf entity.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object to be checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the rdf entity is not a solution.
   */
  public function overviewAccess(RdfInterface $rdf_entity, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    if ($rdf_entity->bundle() !== 'solution') {
      throw new NotFoundHttpException();
    }

    $releases = $rdf_entity->getReleases();
    $access = AccessResult::allowedIf($rdf_entity->access('view', $account, TRUE) && !empty($releases));
    $access->addCacheableDependency($rdf_entity);
    foreach ($releases as $release) {
      $access->addCacheableDependency($release);
    }

    /** @var \Drupal\solution\Entity\SolutionInterface $rdf_entity */
    return $access;
  }

  /**
   * Creates a new asset_release entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution that the asset_release is version of.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   The unsaved asset_release entity.
   */
  protected function createNewAssetRelease(RdfInterface $rdf_entity): RdfInterface {
    /** @var \Drupal\rdf_entity\RdfInterface $release */
    $release = $this->entityTypeManager()->getStorage('rdf_entity')->create([
      'rid' => 'asset_release',
      'field_isr_is_version_of' => $rdf_entity->id(),
    ]);
    return $release;
  }

}
