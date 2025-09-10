<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\babel\BabelStorageInterface;
use Drupal\babel\Model\Source;
use Drupal\locale\StringStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Registers source strings only available for non-privileged users.
 *
 * @todo Drop this string translator in ISAICP-10215.
 */
class JoinupI18nTranslator implements TranslatorInterface, DestructableInterface {

  use DependencySerializationTrait;

  /**
   * Collected strings.
   *
   * @var array<array-key, array<string, string>>
   *   Associative array keyed by context with the list of strings as values.
   */
  protected array $strings = [];

  /**
   * Cached check for the current user's 'bypass enforced english' permission.
   */
  protected bool $hasBypassPermission;

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly Connection $db,
    protected readonly StringStorageInterface $localeStorage,
    protected readonly BabelStorageInterface $babelStorage,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStringTranslation($langcode, $string, $context): string|false {
    if (!isset($this->hasBypassPermission)) {
      // For performance reasons check only once and cache.
      $this->hasBypassPermission = $this->currentUser->hasPermission('bypass enforced english');
    }

    // Non-English translation are covered by Locale module.
    if ($langcode === 'en' && !$this->hasBypassPermission) {
      $this->strings[$context][$string] = $string;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    $additions = [];

    foreach ($this->strings as $context => $strings) {
      $existing = $this->db->select('locales_source', 'ls')
        ->fields('ls', ['source'])
        ->condition('ls.context', $context)
        ->condition('ls.source', $strings, 'IN')
        ->execute()
        ->fetchCol();

      foreach (array_diff($strings, $existing) as $string) {
        $additions[] = [$string, $context];
      }
    }

    if (empty($additions)) {
      return;
    }

    $path = (string) $this->requestStack->getCurrentRequest()?->getRequestUri();

    $sources = [];
    foreach ($additions as [$source, $context]) {
      $locale = $this->localeStorage->createString([
        'source' => $source,
        'context' => $context,
        'version' => \Drupal::VERSION,
      ])->addLocation('path', $path)->save();
      $sources[$locale->lid] = new Source($source, $context);
    }

    // Update Babel backend.
    $this->babelStorage->update('locale', $sources);

    $this->strings = [];
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->strings = [];
  }

}
