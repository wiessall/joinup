<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Form\AnonymousDownloadForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to handle the tracking of distribution downloads.
 */
class DownloadTrackingController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    protected $entityTypeManager,
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
    if (empty($usages['file']['paragraph'])) {
      throw new \RuntimeException('No distributions were found using the file with ID ' . $file->id());
    }
    if (count($usages['file']['paragraph']) > 1) {
      throw new \RuntimeException('More than one distributions were found for the file with ID ' . $file->id());
    }

    $paragraph_id = key($usages['file']['paragraph']);
    $distribution = $this->getDistributionByParagraphId($paragraph_id);
    $parent = $distribution->getParent();

    $event = $this->entityTypeManager->getStorage('download_event')->create([
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
    $paragraph = $this->entityTypeManager
      ->getStorage('paragraph')
      ->loadByProperties([
        'type' => 'resource_file',
        'resource_file' => [
          'target_id' => $file->id(),
        ],
      ]);
    $paragraph_id = array_key_first($paragraph);
    $distribution = $this->getDistributionByParagraphId($paragraph_id);

    if (!$distribution instanceof DistributionInterface) {
      return AccessResult::forbidden();
    }

    return $distribution->access('view', $account, TRUE);
  }

  /**
   * Gets distribution by id of paragraph from resources field.
   *
   * @param int $paragraph_id
   *   Id of paragraph from resources field.
   */
  protected function getDistributionByParagraphId(int $paragraph_id): DistributionInterface {
    $distribution = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'distribution',
        'resources' => [
          'target_id' => $paragraph_id,
        ],
      ]);
    return reset($distribution);
  }

}
