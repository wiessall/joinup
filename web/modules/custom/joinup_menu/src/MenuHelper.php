<?php

declare(strict_types=1);

namespace Drupal\joinup_menu;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\rdf_sync\RdfSyncMapperInterface;

/**
 * Helper methods for working with menus.
 */
class MenuHelper implements MenuHelperInterface {

  use StringTranslationTrait;
  use RedirectDestinationTrait;

  public function __construct(
    protected readonly LoggerChannelFactoryInterface $logger,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RdfSyncMapperInterface $mapper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getBclAnonymousMenu(CacheableMetadata $cacheability): array {
    $anonymous_links = [];

    $anonymous_links[] = [
      'label' => $this->t('Home'),
      'link' => Url::fromRoute('<front>'),
      'hide_on_desktop' => TRUE,
    ];

    $cas_login_link = Url::fromRoute('cas.login', [], [
      'query' => $this->getDestinationArray(),
    ]);

    $anonymous_links[] = [
      'label' => $this->t('Sign in'),
      'link' => $cas_login_link,
      'attributes' => ['class' => ['sign-in-link']],
    ];

    $popover = [
      'cta' => [
        'link' => $cas_login_link,
        'attributes' => 'tabindex="0"',
        'text' => $this->t('Create an account'),
      ],
      'description' => $this->t('As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform.'),
    ];
    $anonymous_links[] = [
      'id' => 'get-started',
      'label' => $this->t('Get started'),
      'popover' => $popover,
    ];

    if ($about_us_link = $this->getBclAboutUsLink($cacheability)) {
      $anonymous_links[] = $about_us_link;
    }

    return $anonymous_links;
  }

  /**
   * Returns the "About us" link pointing to the Joinup collection landing page.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects cacheability for the "About us" link.
   *
   * @return array|null
   *   The "About us" link in the BCL header template format, or NULL when the
   *   Joinup collection does not exist.
   */
  protected function getBclAboutUsLink(CacheableMetadata $cacheability): ?array {
    $cacheability->addCacheTags(['joinup_collection:exists']);
    $joinup_collection = $this->mapper->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    if (!$joinup_collection instanceof CollectionInterface) {
      $this->logger->get('joinup_menu')->warning('"About us" link could not be rendered because the Interoperable Europe Portal collection is not defined or doesn\'t have an About page.');
      return NULL;
    }
    $cacheability->addCacheableDependency($joinup_collection);
    return [
      'label' => $this->t('About us'),
      'link' => $joinup_collection->toUrl(),
      'hide_on_desktop' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOgMenuByGroup(string $menuType, string $groupId): ?OgMenuInstanceInterface {
    /** @var \Drupal\og_menu\OgMenuInstanceInterface[] $ogMenuInstances */
    $ogMenuInstances = $this->entityTypeManager->getStorage('ogmenu_instance')->loadByProperties([
      'type' => $menuType,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $groupId,
    ]);

    return $ogMenuInstances ? reset($ogMenuInstances) : NULL;
  }

}
