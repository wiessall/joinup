<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Event;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Simple data class to store information about group reports.
 *
 * @see \Drupal\joinup_group\Event\GroupReportsEventInterface
 */
readonly class GroupReport {

  public function __construct(
    protected string $id,
    protected TranslatableMarkup $title,
    protected Url $url,
    protected ?TranslatableMarkup $description,
  ) {
  }

  /**
   * Returns the machine name of the group report.
   *
   * @return string
   *   The machine name of the group report.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Returns the title of the group report.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the group report.
   */
  public function getTitle(): TranslatableMarkup {
    return $this->title;
  }

  /**
   * Returns the optional description of the group report.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The optional description of the group report.
   */
  public function getDescription(): ?TranslatableMarkup {
    return $this->description;
  }

  /**
   * Returns the URL of the group report.
   *
   * @return \Drupal\Core\Url
   *   The URL of the group report.
   */
  public function getUrl(): Url {
    return $this->url;
  }

}
