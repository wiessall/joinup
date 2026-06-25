<?php

/**
 * @file
 * Deploy scripts for Topic module.
 */

declare(strict_types=1);

/**
 * Translate the topic block label.
 */
function topic_deploy_201500(): void {
  $entitySubQueue = \Drupal::entityTypeManager()
    ->getStorage('entity_subqueue')
    ->load('homepage_topics');
  tmgmt_content_create_continuous_job_items($entitySubQueue);
}
