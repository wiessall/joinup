<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_release\Entity\Release;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelper;
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
   * @param \Drupal\node\NodeInterface $node
   *   The solution entity.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function add(NodeInterface $node): array {
    return $this->entityFormBuilder()->getForm($this->createNewRelease($node));
  }

  /**
   * Handles access to the release add form through solution pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The RDF entity for which the custom page is created.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The RDF entity for which the custom page is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createReleaseAccess(NodeInterface $node, ?AccountInterface $account = NULL): AccessResultInterface {
    if ($node->bundle() !== 'solution') {
      throw new NotFoundHttpException();
    }

    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('release', $account, [
      // The group will be consumed in joinup_rdf_entity_create_access().
      'group' => $node,
    ], TRUE);
  }

  /**
   * Returns a build array for the solution releases overview page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The solution entity.
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   *   The build array for the page or a 404 response.
   */
  public function overview(NodeInterface $node): TrustedRedirectResponse|array {
    /** @var \Drupal\joinup_solution\SolutionReleasesAndDistributionsFieldItemList $field */
    $field = $node->get('releases_and_distributions');
    if ($field->isEmpty()) {
      return (new TrustedRedirectResponse('/not-found'))
        ->setStatusCode(404)
        ->addCacheableDependency($node);
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
      ->addCacheableDependency($node)
      ->applyTo($build);

    return $build;
  }

  /**
   * Page title callback for the solution releases overview.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The solution entity.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The page title.
   */
  public function overviewPageTitle(NodeInterface $node): MarkupInterface {
    return $this->t('Releases for %solution solution', ['%solution' => $node->label()]);
  }

  /**
   * Access callback for the solution releases overview.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The solution rdf entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the rdf entity is not a solution.
   */
  public function overviewAccess(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    // @todo This is probably not needed now that we have added the bundle to
    //   the route requirement options.
    if ($node->bundle() !== 'solution') {
      throw new NotFoundHttpException();
    }

    $releases = $node->getReleases();
    $access = AccessResult::allowedIf($node->access('view', $account, TRUE) && !empty($releases));
    $access->addCacheableDependency($node);
    foreach ($releases as $release) {
      $access->addCacheableDependency($release);
    }
    return $access;
  }

  /**
   * Creates a new release entity.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $solution
   *   The solution that the release is version of.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface
   *   The unsaved release entity.
   */
  protected function createNewRelease(SolutionInterface $solution): ReleaseInterface {
    return Release::create([
      'type' => 'release',
      OgGroupAudienceHelper::DEFAULT_FIELD => $solution->id(),
    ]);
  }

}
