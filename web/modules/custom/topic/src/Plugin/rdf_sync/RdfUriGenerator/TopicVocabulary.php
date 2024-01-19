<?php

declare(strict_types=1);

namespace Drupal\topic\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\rdf_sync\RdfUriGeneratorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the RDF URI for 'topic' taxonomy terms.
 *
 * @RdfUriGenerator(
 *   id = "topic",
 *   name = @Translation("Topic terms"),
 * )
 */
class TopicVocabulary extends RdfUriGeneratorPluginBase {

  /**
   * Constructs a TopicVocabulary plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected TransliterationInterface $transliteration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('transliteration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $entity = $this->getEntity();

    if (empty($entity->parent->target_id)) {
      $pattern = 'http://joinup.eu/ontology/topic/category#%s';
    }
    else {
      $pattern = 'http://joinup.eu/ontology/topic#%s';
    }

    $name = strtolower($this->transliteration->transliterate($entity->label()));
    // Replace non-alphanumeric characters with dash.
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    // Strip consecutive dashes.
    $name = preg_replace('/\-{2,}/', '-', $name);
    // Trim dash from the begin and the end.
    $name = trim($name, '-');

    return sprintf($pattern, $name);
  }

}
