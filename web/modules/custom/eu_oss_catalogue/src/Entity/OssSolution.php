<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Plugin\Field\OssGitFullNameItemList;
use Drupal\eu_oss_catalogue\Plugin\Field\OssGitNameItemList;
use Drupal\eu_oss_catalogue\Plugin\Field\RelevantLanguageCode;
use Drupal\joinup_oss_catalogue\Plugin\LanguageNegotiation\OssContentNegotiation;
use Drupal\node\Entity\Node;
use Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceItem;

/**
 * Bundle class for 'oss_solution' nodes.
 */
class OssSolution extends Node implements OssSolutionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['oss_git_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('GIT name'))
      ->setDescription(t('This is the Name of the GIT Project, NOT namespaced.'))
      ->setComputed(TRUE)
      ->setClass(OssGitNameItemList::class);

    $fields['oss_git_full_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('GIT full name'))
      ->setDescription(t('This is the Name of the GIT Project, full Name spaced.'))
      ->setComputed(TRUE)
      ->setClass(OssGitFullNameItemList::class);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    /** @var \Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceFieldItemListInterface $source */
    $source = $this->get('oss_source');
    foreach ($source->getValue() as $value) {
      if ($value['plugin_id'] === $provider->getPluginId()) {
        return $this;
      }
    }
    $source->appendItem($provider->getPluginId());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $this->get('oss_source')->filter(function (PluginReferenceItem $value) use ($provider): bool {
      return $value->plugin_id !== $provider->getPluginId();
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelevantLangCode(): string {
    return $this->get(RelevantLanguageCode::FIELD_NAME)->first()->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function isArchived(): bool {
    return $this->get('oss_archived')->getString() === '1';
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    // Prevent translation view access.
    // OSS Solutions are storing translations, but we do not want to expose the
    // corresponding pages in the frontend, only the default translation.
    /* @see content_translation_page_attachments */
    if ($operation === 'view' && !$this->isDefaultTranslation()) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = NULL, array $options = []): Url {
    $url = parent::toUrl($rel, $options);

    // Tell OssContentNegotiation to process edit-form and any content
    // translation URLs during outbound.
    /* @see \Drupal\joinup_oss_catalogue\Plugin\LanguageNegotiation\OssContentNegotiation::processOutbound */
    if (in_array($rel, [
      'edit-form',
      'drupal:content-translation-add',
      'drupal:content-translation-edit',
      'drupal:content-translation-delete',
    ], TRUE)) {
      $url->setOption(OssContentNegotiation::METHOD_ID, TRUE);
    }

    return $url;
  }

}
