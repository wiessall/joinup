<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Hook\AfterStep;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Then;

/**
 * Provides step definitions for taking screenshots.
 *
 * Inspired on ScreenShotTrait from https://github.com/nuvoleweb/drupal-behat.
 *
 * @see https://github.com/nuvoleweb/drupal-behat
 */
class ScreenshotContext extends RawMinkContext {

  public function __construct(
    protected readonly string $localDir,
  ) {}

  /**
   * Saves a screenshot under a given name.
   *
   * @param string|null $name
   *   The file name.
   */
  #[Then('I take a screenshot :name')]
  #[Then('I take a screenshot')]
  public function takeScreenshot(?string $name = NULL): void {
    $name ??= 'screenshot-' . \Drupal::service('password_generator')->generate();
    $message = "Screenshot:";
    $this->createScreenshots($name, $message);
  }

  /**
   * Make sure there is no PHP notice on the screen during tests.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   The event.
   */
  #[AfterStep]
  public function screenshotForPhpNotices(AfterStepScope $event): void {
    $environment = $event->getEnvironment();
    // Make sure the environment has the MessageContext.
    $class = 'Drupal\DrupalExtension\Context\MessageContext';
    if ($environment instanceof InitializedContextEnvironment && $environment->hasContextClass($class)) {
      /** @var \Drupal\DrupalExtension\Context\MessageContext $context */
      $context = $environment->getContext($class);
      // Only check if the session is started.
      if ($context->getMink()->isSessionStarted()) {
        try {
          $context->assertNotWarningMessage('Notice:');
        }
        catch (ExpectationException) {
          $message = "{$this->getStepLine($event)} screenshot(s):";
          $this->createScreenshots($this->buildScreenshotFileName('php-notice', $event), $message);
          // We don't throw $e anymore because we don't fail on the notice.
        }
      }
    }
  }

  /**
   * Takes a screenshot after failed steps (image or html).
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   The event.
   */
  #[AfterStep]
  public function takeScreenshotAfterFailedStep(AfterStepScope $event): void {
    if (!$event->getTestResult()->isPassed()) {
      $message = "{$this->getStepLine($event)} screenshot(s):";
      $this->createScreenshots($this->buildScreenshotFileName('failed', $event), $message);
    }
  }

  /**
   * Creates a screenshot in HTML or PNG format.
   *
   * Does nothing if there was no initial page visit (so, without browser
   * session) because in that case, there is nothing to create screenshot about.
   *
   * @param string $fileName
   *   The filename of the screenshot (complete).
   * @param string $message
   *   The message to be printed.
   *
   * @throws \Exception
   *   When the screenshot cannot be saved although the session was started.
   */
  protected function createScreenshots(string $fileName, string $message): void {
    $driver = $this->getSession()->getDriver();
    if (!$driver->isStarted()) {
      return;
    }
    $screenshot = $this->getSession()->getPage()->getContent();
    $output = [
      $message,
      '- ' . $this->storeScreenshot($screenshot, "$fileName.html"),
    ];

    // For Selenium tests, create an additional .png screenshot.
    if ($driver instanceof Selenium2Driver) {
      try {
        $screenshot = $driver->getScreenshot();
        $output[] = '- ' . $this->storeScreenshot($screenshot, "$fileName.png");
      }
      catch (DriverException) {
        // If the .html screenshot was successful, do nothing.
      }
    }

    print implode("\n", $output) . PHP_EOL;

    // Depending on the output formatter used, Behat will suppress any output
    // generated during the test. Flush the output buffers so our message will
    // show up in the test logs.
    ob_flush();
  }

  /**
   * Stores the screenshot locally and, optionally, in S3.
   *
   * @param string $screenshot
   *   The screenshot content.
   * @param string $fileName
   *   The filename of the screenshot (complete).
   *
   * @return string
   *   The screenshot URL.
   *
   * @throws \Exception
   *   When the screenshot cannot be saved.
   */
  protected function storeScreenshot(string $screenshot, string $fileName): string {
    // Save the screenshot locally.
    $path = $this->save($screenshot, $fileName);
    // Show it as a URL, so that it can be easy to click in IDE or shell.
    return $this->getMinkParameter('base_url') . '/' . $path;
  }

  /**
   * Saves the given screenshot to the local filesystem.
   *
   * @param string $screenshot
   *   The screenshot data.
   * @param string $fileName
   *   The file name.
   *
   * @return string
   *   The saved screenshot path.
   *
   * @throws \Exception
   *   Thrown if the destination folder doesn't exist and couldn't be created.
   */
  protected function save(string $screenshot, string $fileName): string {
    // Ensure the directory exists.
    $dir = rtrim($this->localDir, DIRECTORY_SEPARATOR);

    if (!is_dir($dir) && !mkdir($dir, 0755, TRUE)) {
      throw new \Exception("The '$dir' folder does not exist and could not be created.");
    }

    $path = $dir . DIRECTORY_SEPARATOR . $fileName;
    file_put_contents($path, $screenshot);
    if (!$pathAbsolute = realpath($path)) {
      throw new \Exception("The screenshot file $path could not be created");
    }

    if (!str_starts_with($pathAbsolute, DRUPAL_ROOT)) {
      throw new \InvalidArgumentException("The behat.screenshots.local.dir config should point to a directory beneath Drupal webroot. Actual value: $this->localDir");
    }

    return trim(str_replace(DRUPAL_ROOT, '', $pathAbsolute), DIRECTORY_SEPARATOR);
  }

  /**
   * Builds a filename from the feature path and failed step line.
   *
   * @param string $type
   *   The type of event (failure, PHP notice, etc).
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   The after step event.
   *
   * @return string
   *   The screenshot filename.
   */
  protected function buildScreenshotFileName(string $type, AfterStepScope $event): string {
    $stepLine = str_replace('/', '--', ltrim(substr($event->getFeature()->getFile(), strlen($event->getEnvironment()->getSuite()->getSetting('paths')[0])), '/'));
    return "$type--$stepLine--{$event->getStep()->getLine()}";
  }

  /**
   * Returns a concatenation of the feature file name and the failed step line.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   The after step event.
   *
   * @return string
   *   A concatenation of the feature file name and the failed step line.
   */
  protected function getStepLine(AfterStepScope $event): string {
    // Remove the common path for readability reasons.
    return ltrim(substr($event->getFeature()->getFile(), strlen($event->getEnvironment()->getSuite()->getSetting('paths')[0])), '/') . ":{$event->getStep()->getLine()}";
  }

}
