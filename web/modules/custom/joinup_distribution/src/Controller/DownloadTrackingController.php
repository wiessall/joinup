<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file_url\FileUrlHandler;
use Drupal\joinup_distribution\Form\AnonymousDownloadForm;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to handle the tracking of distribution downloads.
 */
class DownloadTrackingController extends ControllerBase {

  /**
   * Instantiates a new DownloadTrackingController object.
   *
   * @param \Drupal\node\NodeStorageInterface $nodeStorage
   *   The RDF entity storage.
   * @param \Drupal\Core\Entity\ContentEntityStorageInterface $eventStorage
   *   The download event entity storage.
   * @param \Drupal\file_url\FileUrlHandler $fileUrlHandler
   *   The file URL handler service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $fileUsage
   *   The file usage service.
   */
  public function __construct(
    protected NodeStorageInterface $nodeStorage,
    protected ContentEntityStorageInterface $eventStorage,
    protected FileUrlHandler $fileUrlHandler,
    protected FileUsageInterface $fileUsage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('entity_type.manager')->getStorage('download_event'),
      $container->get('file_url.handler'),
      $container->get('file.usage')
    );
  }

  /**
   * Tracks a distribution file download done by a user.
   *
   * @param \Drupal\file\FileInterface $file
   *   The distribution file that has been downloaded.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The generated response or the form array.
   */
  public function trackDownload(FileInterface $file): array|AjaxResponse {
    return $this->currentUser()->isAnonymous()
      ? $this->trackAnonymousDownload($file)
      : $this->trackAuthenticatedDownload($file);
  }

  /**
   * Tracks a download done by an anonymous user.
   *
   * @param \Drupal\file\FileInterface $file
   *   The distribution file that has been downloaded.
   *
   * @return array
   *   The form array.
   */
  protected function trackAnonymousDownload(FileInterface $file): array {
    return $this->formBuilder()->getForm(AnonymousDownloadForm::class, $file);
  }

  /**
   * Tracks a download done by an registered user user.
   *
   * @param \Drupal\file\FileInterface $file
   *   The distribution file that has been downloaded.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The generated response.
   */
  protected function trackAuthenticatedDownload(FileInterface $file): AjaxResponse {
    $usages = $this->fileUsage->listUsage($file);

    // Normally, only one distribution is allowed to use a file and only
    // distributions call this code.
    if (empty($usages['file']['node'])) {
      throw new \RuntimeException('No distributions were found using the file with ID ' . $file->id());
    }
    if (count($usages['file']['node']) > 1) {
      throw new \RuntimeException('More than one distributions were found for the file with ID ' . $file->id());
    }
    $distribution = $this->nodeStorage->load(key($usages['file']['node']));

    /** @var \Drupal\joinup_solution\Entity\SolutionInterface|\Drupal\joinup_release\Entity\ReleaseInterface $parent */
    $parent = $distribution->getParent();

    $event = $this->eventStorage->create([
      'uid' => $this->currentUser()->id(),
      'file' => $file->id(),
      'parent_entity_type' => $parent->getEntityTypeId(),
      'parent_entity_id' => $parent->id(),
    ]);
    $event->save();

    $response = new AjaxResponse();
    $response->setStatusCode(Response::HTTP_NO_CONTENT);

    return $response;
  }

  /**
   * Access callback that checks that the file is a valid distribution one.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file being downloaded.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whom to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed when the file exists, neutral otherwise.
   */
  public function isDistributionFile(FileInterface $file, AccountInterface $account): AccessResultInterface {
    $query = $this->nodeStorage->getQuery()->accessCheck(FALSE);
    $file_url_handler = $this->fileUrlHandler;

    // Verify that the file exists and it's attached to a solution.
    $query->condition($this->nodeStorage->getEntityType()->getKey('bundle'), 'distribution')
      ->condition('access_url', $file_url_handler::fileToUrl($file));
    $results = $query->execute();

    if (empty($results)) {
      return AccessResult::forbidden();
    }

    $entity = $this->nodeStorage->load(array_pop($results));

    return $entity->access('view', $account, TRUE);
  }

}
