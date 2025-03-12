<?php

declare(strict_types=1);

namespace Drupal\joinup\Listener;

use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\EventDispatcher\Event\BeforeFeatureTested;
use Behat\Behat\EventDispatcher\Event\FeatureTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Testwork\Counter\Timer;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Joinup\Traits\DigitQaPipelineAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
   * Whether this listener should run.
   *
   * @var bool
   */
  private bool $enabled = TRUE;

  /**
   * Stores execution times.
   *
   * @var array
   */
  private array $timerStore = [];

  public function __construct(
    protected string $basePath,
    protected InputInterface $input,
    protected OutputInterface $output,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExerciseCompleted::BEFORE => 'beforeExercise',
      ExerciseCompleted::AFTER => 'afterExercise',
      FeatureTested::BEFORE => 'beforeFeature',
      FeatureTested::AFTER => 'afterFeature',
    ];
  }

  /**
   * Setup.
   */
  public function beforeExercise(): void {
    $dry_run = $this->input->getOption('dry-run');
    $this->enabled = !$dry_run && (self::isDigitQaPipeline() || getenv('JOINUP_BEHAT_STATS'));
  }

  /**
   * Teardown.
   */
  public function afterExercise(): void {
    if (!$this->enabled) {
      return;
    }

    if (empty($this->timerStore)) {
      return;
    }

    // Collect results.
    $results = [];
    foreach ($this->timerStore as $group => $features) {
      arsort($features);
      $results[$group]['total'] = number_format(array_sum($features), 2) . 's';
      $results[$group]['features'] = array_map(static fn($v) => number_format($v, 2) . 's', $features);
    }

    // Print results.
    $this->output->write(
      '--------------------------------------------------------------------' . PHP_EOL
      . 'Execution times:' . PHP_EOL
      . Yaml::dump($results, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
      . '--------------------------------------------------------------------' . PHP_EOL
    );
  }

  /**
   * Starts the timer for this feature.
   */
  public function beforeFeature(BeforeFeatureTested $event): void {
    if (!$this->enabled) {
      return;
    }

    $group = $this->getFeatureGroup($event->getFeature());
    $feature = $this->getFeaturePath($event->getFeature());

    $timer = new Timer();
    $timer->start();
    $this->timerStore[$group][$feature] = $timer;
  }

  /**
   * Stops the timer for this feature.
   */
  public function afterFeature(AfterFeatureTested $event): void {
    if (!$this->enabled) {
      return;
    }

    $group = $this->getFeatureGroup($event->getFeature());
    $feature = $this->getFeaturePath($event->getFeature());

    $timer = $this->timerStore[$group][$feature] ?? NULL;
    if ($timer instanceof Timer) {
      $timer->stop();
      $this->timerStore[$group][$feature] = $timer->getTime();
    }
  }

  /**
   * Get the feature group (group-a, group-b, group-c,...).
   */
  private function getFeatureGroup(FeatureNode $feature): string {
    foreach ($feature->getTags() as $tag) {
      if (str_starts_with($tag, 'group-')) {
        return $tag;
      }
    }
    return 'default';
  }

  /**
   * Get the path to the feature.
   */
  private function getFeaturePath(FeatureNode $feature): string {
    $path = sprintf('%s:%d', $feature->getFile(), $feature->getLine());
    return str_replace($this->basePath . DIRECTORY_SEPARATOR, '', $path);
  }

}
