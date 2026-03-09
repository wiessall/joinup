<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\Then;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions for testing licences.
 */
class JoinupLicenceContext extends RawDrupalContext {

  use AssertTrait;
  use EntityTrait;

  /**
   * Navigates to the canonical page display of a licence.
   *
   * @param string $licence
   *   The title of the licence.
   * @param string $format
   *   The RDF serialization format.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[When('I go to (the homepage of )the :licence licence in the :format serialisation')]
  public function visitLicenceWithFormat(string $licence, string $format): void {
    $entity = $this->getEntityByLabel('node', $licence);
    $this->visitPath($entity->toUrl('canonical', ['query' => ['_format' => $format]])->toString());
  }

  /**
   * Converts yes/no strings to boolean for 'deprecated' licence field.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $event
   *   The before node create event.
   *
   * @BeforeNodeCreate
   */
  public function convertToBoolean(BeforeNodeCreateScope $event): void {
    $node = $event->getEntity();
    if ($node->type === 'licence' && !empty($node->deprecated)) {
      $value = ['yes' => TRUE, 'no' => FALSE][$node->deprecated] ?? NULL;
      if ($value === NULL) {
        throw new \InvalidArgumentException("Invalid licence deprecated value: '$node->deprecated'");
      }
      $node->deprecated = $value;
    }
  }

  /**
   * Checks the number of available licences.
   *
   * @param int $number
   *   The expected number of licences.
   */
  #[Then('I should have :number licence(s)')]
  public function assertLicenceCount(int $number): void {
    $actual = \Drupal::entityQuery('node')->accessCheck(FALSE)->count()->condition('type', 'licence')->execute();
    $this->assertSameValues($number, $actual);
  }

}
