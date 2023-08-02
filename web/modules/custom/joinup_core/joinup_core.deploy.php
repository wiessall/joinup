<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Update the topics list.
 */
function joinup_core_deploy_109600(): string {
  $vocabulary_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $messages = [];

  // The former Innovation must be renamed to 'Digital Innovation' and keep the
  // linked content items.
  $vocabulary_manager->renameTopic('http://joinup.eu/ontology/topic#innovation', 'Digital Innovation');
  $messages[] = 'Topic "Innovation" renamed to "Digital Innovation".';
  $vocabulary_manager->changeTopicId('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $vocabulary_manager->updateDbReference('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $messages[] = 'Topic ID "http://joinup.eu/ontology/topic#innovation" changed to "http://joinup.eu/ontology/topic#digital-innovation".';

  // A new parent category must be created, named 'Innovation'.
  $vocabulary_manager->createTopicTerm('Innovation');
  $messages[] = 'Topic category "Innovation" created.';

  // Under the new parent category, the following topics must be moved:
  // * Artificial Intelligence (move it by ICT parent topic).
  // * Blockchain (move it by ICT parent topic).
  // * 'Digital Innovation' (former Innovation).
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#artificial-intelligence', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#blockchain', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#digital-innovation', 'http://joinup.eu/ontology/topic/category#innovation');
  $messages[] = 'Topics "Artificial Intelligence", "Blockchain" and "Digital Innovation" moved to "Innovation" category.';

  // Under the new parent category, the following topics must be added:
  // * Advanced data analytics
  // * AI Chatbot
  // * AI Facial recognition
  // * AI Image generation
  // * AI Machine translation
  // * AI Named entity recognition
  // * AI Speech recognition
  // * AI Video analytics
  // * Extended reality
  // * High performance computing
  // * Indoor position systems
  // * Internet of Things
  // * Optical Character Recognition
  // * Robotic Process Automation
  $labels = [
    'Advanced data analytics',
    'AI Chatbot',
    'AI Facial recognition',
    'AI Image generation',
    'AI Machine translation',
    'AI Named entity recognition',
    'AI Speech recognition',
    'AI Video analytics',
    'Extended reality',
    'High performance computing',
    'Indoor position systems',
    'Internet of Things',
    'Optical Character Recognition',
    'Robotic Process Automation',
  ];

  foreach ($labels as $label) {
    $vocabulary_manager->createTopicTerm($label, 'http://joinup.eu/ontology/topic/category#innovation');
    $messages[] = "Topic \"$label\" created.";
  }

  return implode(PHP_EOL, $messages);
}
