<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for release forms.
 */
class ReleaseController extends ControllerBase {

  /**
   * Controller for the base form.
   *
   * We need to override the functionality of the create form for pages
   * that include the rdf_entity id in the url so that the OG audience field
   * is auto completed.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution rdf_entity.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function add(RdfInterface $rdf_entity): array {
    return $this->entityFormBuilder()->getForm($this->createNewRelease($rdf_entity));
  }

  /**
   * Handles access to the release add form through solution pages.
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

    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('release', $account, [
      // @todo This should be consumed somewhere else. Revisit this when all
      //   changes are completed.
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
  public function overview(RdfInterface $rdf_entity): TrustedRedirectResponse|array {
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
    // @see joinup_release_preprocess_node()
    foreach ($entities as $entity) {
      if ($entity->bundle() === 'release') {
        $entity->is_latest_release = TRUE;
        break;
      }
    }

    $build = [
      [
        '#theme' => 'joinup_release_releases_download',
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
    // @todo This is probably not needed now that we have added the bundle to
    //   the route requirement options.
    if ($rdf_entity->bundle() !== 'solution') {
      throw new NotFoundHttpException();
    }

    $releases = $rdf_entity->getReleases();
    $access = AccessResult::allowedIf($rdf_entity->access('view', $account, TRUE) && !empty($releases));
    $access->addCacheableDependency($rdf_entity);
    foreach ($releases as $release) {
      $access->addCacheableDependency($release);
    }
    return $access;
  }

  /**
   * Creates a new release entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The solution that the release is version of.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface
   *   The unsaved release entity.
   */
  protected function createNewRelease(RdfInterface $rdf_entity): ReleaseInterface {
    /** @var \Drupal\rdf_entity\RdfInterface $release */
    return $this->entityTypeManager()->getStorage('node')->create([
      'type' => 'release',
      'is_version_of' => $rdf_entity->id(),
    ]);
  }

}
