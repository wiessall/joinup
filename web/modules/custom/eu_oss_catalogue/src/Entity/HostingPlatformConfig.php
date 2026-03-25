<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Form\HostingPlatformConfigForm;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Stores the configuration of a 'hosting_platform' plugin instance.
 */
#[ConfigEntityType(
  id: 'hosting_platform',
  label: new TranslatableMarkup('Code hosting platform config'),
  handlers: [
    'form' => [
      'edit' => HostingPlatformConfigForm::class,
      'delete' => HostingPlatformConfigDeleteForm::class,
    ],
    'route_provider' => [
      'html' =>AdminHtmlRouteProvider::class,
    ],
    'list_builder' => ConfigEntityListBuilder::class,
  ],
  admin_permission: 'administer eu oss catalogue',
  config_prefix: 'hosting_platform',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  links: [
    'edit-form' => '/admin/content/eu-oss-catalogue/hosting-platform/manage/{hosting_platform}',
    'delete-form' => '/admin/content/eu-oss-catalogue/hosting-platform/manage/{hosting_platform}/delete',
  ],
  config_export: [
    'id',
    'label',
    'type',
    'weblinks',
    'country',
    'description',
    'platformId',
    'platformSettings',
  ],
)]
class HostingPlatformConfig extends ConfigEntityBase implements HostingPlatformConfigInterface {

  /**
   * Entity ID.
   */
  protected string $id;

  /**
   * A meaningful label.
   */
  protected string $label;

  /**
   * The catalogue type.
   */
  protected string $type;

  /**
   * List of weblinks.
   */
  protected array $weblinks = [];

  /**
   * The country code.
   */
  protected ?string $country = NULL;

  /**
   * The description.
   */
  protected ?string $description = NULL;

  /**
   * The code hosting platform plugin ID.
   */
  protected string $platformId;

  /**
   * The code hosting platform plugin instance settings.
   */
  protected array $platformSettings = [];

  /**
   * The code platform lazy plugin collection.
   */
  protected DefaultSingleLazyPluginCollection $platform;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCatalogueType(): CatalogueType {
    return CatalogueType::from($this->type);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeblinks(): array {
    return $this->weblinks;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountryCode(): ?string {
    return $this->country;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformPluginId(): string {
    return $this->platformId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformSettings(): array {
    return $this->platformSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'platformSettings' => isset($this->platformId) ? $this->getPlatform() : NULL,
    ];
  }

  /**
   * Returns the code hosting platform plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The code hosting platform plugin collection.
   */
  protected function getPlatform(): DefaultSingleLazyPluginCollection {
    if (!isset($this->platform)) {
      $platformManager = \Drupal::getContainer()->get('plugin.manager.eu_oss_catalogue.hosting_platform');
      $this->platform = new DefaultSingleLazyPluginCollection($platformManager, $this->platformId, $this->platformSettings);
    }
    return $this->platform;
  }

  /**
   * Config validation callback for weblinks.
   *
   * @param array $values
   *   The configuration.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation execution context.
   */
  public static function validateWeblinks(array $values, ExecutionContextInterface $context): void {
    if (empty($values)) {
      $context->buildViolation('It is required')
        ->atPath('weblinks')
        ->addViolation();
    }

    foreach ($values as $weblink) {
      $url = $weblink['url'] ?? '';

      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $context->buildViolation('Invalid URL: @url')
          ->setParameter('@url', $url)
          ->atPath('weblinks')
          ->addViolation();
      }
    }
  }

}
