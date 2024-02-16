<?php

declare(strict_types=1);

namespace Drupal\developers_italia\Plugin\pipeline\Pipeline;

use Drupal\joinup_federation\JoinupFederationPipelinePluginBase;

/**
 * The data pipeline for the EU SIC collection.
 *
 * @PipelinePipeline(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
 *   steps = {
 *     "manual_upload",
 *     "remove_unsupported_data",
 *     "add_joinup_vocabularies",
 *     "adms_validation",
 *     "analyze_incoming_entities",
 *     "spdx_to_joinup_licence",
 *     "user_selection_filter",
 *     "3_way_merge",
 *     "broken_references",
 *     "joinup_validation",
 *     "import",
 *     "provenance_activity",
 *   },
 * )
 */
class DevelopersItaliaPipeline extends JoinupFederationPipelinePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getCollection(): string {
    return 'http://data.europa.eu/w21/de741a91-cde3-471c-8ffe-0c5347aa0bee';
  }

}
