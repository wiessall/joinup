<?php

declare(strict_types=1);

namespace Drupal\joinup\Listener;

use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\EventDispatcher\Event\BeforeFeatureTested;
use Behat\Behat\EventDispatcher\Event\FeatureTested;
use Behat\Gherkin\Node\TaggedNodeInterface;
use Behat\Testwork\Counter\Timer;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Joinup\Traits\DigitQaPipelineAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Reports all groups and features execution times.
 *
 * Set JOINUP_BEHAT_STATS=true in .env to enable, enabled on Gitlab by default.
 * Useful to get info in order to split behat features into groups on Gitlab.
 * Run all clean tests: time ddev exec ./vendor/bin/behat --profile=clean
 * Warning: This keeps your environment/machine stranded for at least 1 hour.
 * Then ensure all groups are balanced and have similar execution times.
 */
class FeatureTimeLoggerListener implements EventSubscriberInterface {

  use DigitQaPipelineAwareTrait;

  /**
   * Stores execution times.
   *
   * @var array
   */
  private array $timerStore = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $enabled = self::isDigitQaPipeline() || getenv('JOINUP_BEHAT_STATS');
    return $enabled ? [
      FeatureTested::BEFORE => 'beforeFeature',
      FeatureTested::AFTER => 'afterFeature',
      ExerciseCompleted::AFTER => 'afterExercise',
    ] : [];
  }

  /**
   * Starts the timer for this feature.
   */
  public function beforeFeature(BeforeFeatureTested $event): void {
    $group = $this->getGroup($event->getFeature());
    $feature = sprintf('%s:%d', $event->getFeature()->getFile(), $event->getFeature()->getLine());

    $timer = new Timer();
    $timer->start();
    $this->timerStore[$group][$feature] = $timer;
  }

  /**
   * Stops the timer for this feature.
   */
  public function afterFeature(AfterFeatureTested $event): void {
    $group = $this->getGroup($event->getFeature());
    $feature = sprintf('%s:%d', $event->getFeature()->getFile(), $event->getFeature()->getLine());

    $timer = $this->timerStore[$group][$feature] ?? NULL;
    if ($timer instanceof Timer) {
      $timer->stop();
      $this->timerStore[$group][$feature] = $timer->getTime();
    }
  }

  /**
   * Prints all features execution times.
   */
  public function afterExercise(): void {
    if (empty($this->timerStore)) {
      return;
    }

    // Gather results.
    $results = [];
    foreach ($this->timerStore as $group => $features) {
      $results[$group]['total'] = array_sum($features);
      $results[$group]['features'] = $features;
    }

    // Print results.
    print "--------------------------------------------------------------------" . PHP_EOL;
    print "Execution times:" . PHP_EOL;
    print Yaml::dump($results, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    print "--------------------------------------------------------------------" . PHP_EOL;
  }

  /**
   * Get the Portal group for the scenario.
   */
  private function getGroup(TaggedNodeInterface $feature): string {
    foreach ($feature->getTags() as $tag) {
      if (str_starts_with($tag, 'group-')) {
        return $tag;
      }
    }
    return 'default';
  }

}
