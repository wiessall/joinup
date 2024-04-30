<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions related to input filter.
 */
class InputFilterContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Default pcre backtrack limit.
   *
   * @var string
   */
  protected string $pcreBacktrackLimit;

  /**
   * Set long body for news page.
   *
   * @param string $title
   *   The name of the news page.
   *
   * @When I set long body of :title news
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setLongBodyForNewsPage(string $title): void {
    $file = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR . 'filter.url-input-with-long-data-image.txt';

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntityByLabel('node', $title, 'news');
    $node->body->value = file_get_contents($file);
    $node->save();
  }

  /**
   * Enable backtrack limit.
   *
   * @BeforeScenario @backtrackLimit
   */
  public function enableBacktrackLimit(): void {
    $this->pcreBacktrackLimit = ini_get('pcre.backtrack_limit');

    ini_set('pcre.backtrack_limit', 1000);
  }

  /**
   * Disable backtrack limit.
   *
   * @AfterScenario @backtrackLimit
   */
  public function disableBacktrackLimit(): void {
    ini_set('pcre.backtrack_limit', $this->pcreBacktrackLimit);
  }

}
