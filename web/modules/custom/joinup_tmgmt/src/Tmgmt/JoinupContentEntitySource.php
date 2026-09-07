<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt\Tmgmt;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\joinup_tmgmt\ExtractedDataMemo;
use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts the translatable data of a source once per request.
 *
 * @see \Drupal\joinup_tmgmt\Hook\JoinupTmgmtHooks::sourcePluginInfoAlter()
 */
class JoinupContentEntitySource extends ContentEntitySource {

  /**
   * What was extracted last.
   */
  protected ExtractedDataMemo $memo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->memo = $container->get(ExtractedDataMemo::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(ContentEntityInterface $entity) {
    return $this->memo->remember($entity, parent::extractTranslatableData(...));
  }

}
