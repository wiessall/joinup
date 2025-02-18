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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to handle the tracking of distribution downloads.
 */
class DownloadTrackingController extends ControllerBase {

  public function __construct(
    protected readonly NodeStorageInterface $nodeStorage,
    protected readonly ContentEntityStorageInterface $eventStorage,
    #[Autowire(service: 'file_url.handler')]
    protected readonly FileUrlHandler $fileUrlHandler,
    #[Autowire(service: 'file.usage')]
    protected readonly FileUsageInterface $fileUsage,
  ) {}

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
