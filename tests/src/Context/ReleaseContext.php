<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;

/**
 * Behat step definitions for testing releases.
 */
class ReleaseContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use EntityTrait;
  use FileTrait;
  use SearchTrait;
  use RdfEntityTrait;

  /**
   * Navigates to the canonical page display of a release.
   *
   * @param string $release
   *   The name of the release.
   *
   * @When I go to (the homepage of )the :release release
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitRelease(string $release): void {
    $entity = $this->getEntityByLabel('node', $release);
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Handles release specific fields.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The scope object.
   *
   * @BeforeNodeCreate
   */
  public function massageFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    if ($scope->getEntity()->type !== 'release') {
      return;
    }

    if (!empty($node->documentation)) {
      foreach ($node->documentation as &$filename) {
        if (empty($filename)) {
          continue;
        }

        $filename = [$this->createFile($filename)->id()];
      }
    }
  }

  /**
   * Deletes a release.
   *
   * @param string $release
   *   The name of the release.
   *
   * @When I delete the :release release
   */
  public function deleteAssetRelease(string $release): void {
    $release = $this->getEntityByLabel('node', $release);
    $release->skip_notification = TRUE;
    $release->delete();
  }

  /**
   * Checks the number of available releases.
   *
   * @param int $number
   *   The expected number of releases.
   *
   * @throws \Exception
   *   Throws an exception when the expected number does not match the one
   *   found.
   *
   * @Then I should have :number release(s)
   */
  public function assertReleaseCount(int $number): void {
    $this->assertEntityCount('node', 'release', $number);
  }

  /**
   * Asserts that releases are shown in the page in the exact order.
   *
   * This step is meant for the releases overview page within a solution.
   *
   * @param \Behat\Gherkin\Node\TableNode $release_table
   *   The asset release titles.
   *
   * @throws \Exception
   *    Thrown when a wrong number of releases is found or the releases are not
   *    ordered properly.
   *
   * @Given I should see the (following )releases in the exact order:
   */
  public function assertReleasesAndOrder(TableNode $release_table): void {
    $releases = [];

    // Replace the column aliases with the actual field names.
    foreach ($release_table->getColumnsHash() as $title) {
      $releases[] = $title['release'];
    }

    $release_titles = $this->getSession()->getPage()->findAll('css', '.releases__item h2.releases__item-title > a');
    if (count($release_titles) != count($releases)) {
      throw new \Exception("Wrong number of releases found.");
    }

    foreach ($releases as $index => $title) {
      if ($release_titles[$index]->getText() !== $title) {
        throw new \Exception("Title {$releases[$index]} is not in the correct order.");
      }
    }
  }

  /**
   * Checks that a release is marked as the latest one.
   *
   * This step is meant for the releases overview page within a solution.
   *
   * @param string $release
   *   The name of the release.
   *
   * @throws \Exception
   *    Thrown if the release is not marked as latest in the page or not found.
   *
   * @Then the :release release should be marked as the latest release
   */
  public function assertReleaseIsLatest(string $release): void {
    $release = $this->getEntityByLabel('node', $release);

    $latest_releases = $this->getSession()->getPage()->findAll('css', '.is-latest');
    if (empty($latest_releases)) {
      throw new \Exception('No release marked as latest was found in the page.');
    }

    if (count($latest_releases) > 1) {
      throw new \Exception('Multiple releases marked as latest were found in the page.');
    }

    $release_version = $release->getVersion();
    $name_with_version = $release->label() . " " . $release_version;

    $latest_release = reset($latest_releases);
    if ($latest_release->find('css', '.releases__item-title')->getText() !== $name_with_version) {
      throw new \Exception("{$release->label()} is not marked as the latest release.");
    }
  }

}
