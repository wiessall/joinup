<?php

declare(strict_types = 1);

namespace Drupal\interoperable_europe\Plugin\Condition;

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup_event\Entity\EventInterface;
use Drupal\joinup_group\Plugin\Condition\GroupContextCondition;
use Drupal\joinup_news\Entity\NewsInterface;

/**
 * Temporary replacements of 'group_context' condition plugin class.
 *
 * This is a temporary solution to check the IOP context while still excluding
 * some group content page types, such as news and event canonical page.
 *
 * @todo Replace usage of this plugin class in ISAICP-7253 and ISAICP-7255.
 *
 * @see interoperable_europe_condition_info_alter()
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7253
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7255
 */
class TemporaryIopCondition extends GroupContextCondition {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $result = parent::evaluate();

    // Only check for exceptions if we're in the IOP group context.
    $config = $this->getConfiguration();
    if ($result && $config['group_context'] && $config['limit_to_groups'] === [InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID]) {
      $route_match = \Drupal::routeMatch();
      // Except news item and event canonical pages.
      if ($route_match->getRouteName() === 'entity.node.canonical' && ($node = $route_match->getParameter('node')) && ($node instanceof EventInterface || $node instanceof NewsInterface)) {
        return FALSE;
      }
    }

    return $result;
  }

}
