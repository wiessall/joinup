<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eu_oss_catalogue\Traits\EuOssCatalogueTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the GitLab code hosting provider.
 */
class FetchCitLabTest extends BrowserTestBase {


  use DrushTestTrait;
  use NodeCreationTrait;
  use EuOssCatalogueTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'eu_oss_catalogue_test',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createTestingTerms([
      'oss_licence' => ['AGPL-3.0-or-later'],
      'oss_category' => ['data-collection', 'data-analytics'],
    ]);
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests fetching all solutions from a GitLab self-hosting platform.
   */
  public function testSelfHostedAll(): void {
    \Drupal::service('eu_oss_catalogue')->fetch('hosting_platform:gitlab_self_hosted_all');

    // Check how incoming data has been queued.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    // Queue items have been released.
    $this->assertSame(0, $queue->numberOfItems());

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution1 */
    $solution1 = $this->getNodeByTitle('Project 1');
    $this->assertNotFalse($solution1);
    $this->assertSame('oss_solution', $solution1->bundle());
    $this->assertSame('https://all.self-hosted.gitlab.example.com/group1/group1_2/group1_3/project1', $solution1->get('oss_git_url')->uri);
    $this->assertSame('1.4.5', $solution1->get('oss_software_version')->value);
    $this->assertSame('0.2', $solution1->get('oss_publiccode_yml_version')->value);
    $this->assertSame('2022-10-07', $solution1->get('oss_release_date')->value);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution2 */
    $solution2 = $this->getNodeByTitle('Project 2');
    $this->assertNotFalse($solution2);
    $this->assertSame('oss_solution', $solution2->bundle());
    $this->assertSame('https://all.self-hosted.gitlab.example.com/group2/group2_2/group2_3/project2', $solution2->get('oss_git_url')->uri);
    $this->assertSame('7.0.2-alpha1', $solution2->get('oss_software_version')->value);
    $this->assertSame('0.2', $solution2->get('oss_publiccode_yml_version')->value);
    $this->assertSame('2021-08-05', $solution2->get('oss_release_date')->value);
  }

}
