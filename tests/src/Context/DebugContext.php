<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Debug related step definitions.
 */
class DebugContext extends RawDrupalContext {

  /**
   * Prints system message from the page for debugging.
   */
  #[Then('/^print messages$/')]
  public function printMessages(): void {
    $selector = $this->getParameter('selectors')['messages']['default'];
    /** @var \Behat\Mink\Element\NodeElement $message */
    foreach ($this->getSession()->getPage()->findAll("css", $selector) as $message) {
      echo $message->getText() . "\n\n";
    }
  }

  /**
   * Asserts that no orphaned content exists in the website.
   */
  #[Then('/^no orphaned content should exist in the website$/')]
  public function noOprhanedContentShouldExistInTheWebsite(): void {
    $query = <<<SQL
      SELECT n1.nid, n1.type, n1.title, n2.type, n2.title
      FROM node_field_data n1
        LEFT JOIN node__og_audience oa ON n1.nid = oa.entity_id
        LEFT JOIN node_field_data n2 ON oa.og_audience_target_id = n2.nid
      WHERE n1.type IN('news', 'discussion', 'document', 'event', 'solution', 'distribution', 'release')
        AND n2.nid IS NULL
      SQL;

    $nids = \Drupal::database()->query($query)->fetchCol();
    if (!empty($nids)) {
      throw new \Exception('Orphaned content found: ' . implode(', ', $nids));
    }
  }

}
