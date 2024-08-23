<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Traits;

use Drupal\Core\Cache\Cache;

/**
 * Provides a trait for group block cache.
 */
trait GroupPluginCacheTrait {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!$this->hasContextDefinition('og')) {
      return parent::getCacheTags();
    }

    // We need to invalidate the cache whenever the parent group changes since
    // the available options in the add content block depend on certain settings
    // of the parent collection, such as the workflow status and the content
    // creation option.
    $group = $this->getContext('og')->getContextValue();
    return Cache::mergeTags(parent::getCacheTags(), $group->getCacheTagsToInvalidate());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $context = parent::getCacheContexts();
    // The links are only visible for certain roles on certain collections.
    // Normally cache contexts are added automatically but these links depend on
    // an optional context which we manage ourselves.
    return Cache::mergeContexts($context, [
      // We vary by the RDF entity type that is in the current context (asset
      // release, collection or solution) because the options shown in the menu
      // are different for each of these bundles.
      'og_group_context',
      // We vary by OG role since a non-member is not allowed to add content.
      'og_role',
      // We vary by user role since a moderator has the ability to add licenses.
      'user.roles',
    ]);
  }

  /**
   * Checks if the context exists in the definitions.
   *
   * This is used instead of the ::getContextDefinition() method to avoid
   * throwing an exception when the context does not exist.
   *
   * @return bool
   *   TRUE if the context exists, FALSE otherwise.
   */
  protected function hasContextDefinition(string $context): bool {
    return isset($this->getContextDefinitions()[$context]);
  }

}
