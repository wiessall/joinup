<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\og\OgContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OSS FAQ block.
 *
 * @Block(
 *   id = "joinup_oss_faq",
 *   admin_label = @Translation("OSS FAQ"),
 *   category = @Translation("OSS Catalogue")
 * )
 */
class OssFaqBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new OssFooterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\OgContextInterface $ogContext
   *   The OG context provider.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected OgContextInterface $ogContext) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.context'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $faqs = [
      [
        'title' => $this->t('Lorem ipsum dolor sit amet ?'),
        'description' => $this->t('Lorem ipsum dolor sit amet......'),
      ],
      [
        'title' => $this->t('Lorem ipsum dolor sit amet ?'),
        'description' => $this->t('Lorem ipsum dolor sit amet......'),
      ],
      [
        'title' => $this->t('Lorem ipsum dolor sit amet ?'),
        'description' => $this->t('Lorem ipsum dolor sit amet......'),
      ],
      [
        'title' => $this->t('Lorem ipsum dolor sit amet ?'),
        'description' => $this->t('Lorem ipsum dolor sit amet......'),
      ],
    ];

    $group = $this->ogContext->getGroup();
    $link = $group ? Link::createFromRoute($this->t('Contact us!'), 'entity.rdf_entity.contact', ['rdf_entity' => $group->id()]) : NULL;

    return [
      '#theme' => 'oss_faqs',
      '#title' => $this->t('Frequently Asked Questions (FAQ)'),
      '#items' => $faqs,
      '#help_text' => $this->t('Or if you need any help'),
      '#contact_us' => $link,
    ];
  }

}
