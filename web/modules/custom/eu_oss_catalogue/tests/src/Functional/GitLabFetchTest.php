<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the GitLab code hosting provider.
 *
 * @group eu_oss_catalogue
 */
class GitLabFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'MIT', 'EUPL-1.2'],
    'oss_category' => ['data-collection', 'data-analytics', 'nothing'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests fetching all solutions from a GitLab self-hosting platform.
   */
  public function testSelfHostedAll(): void {
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_all', 2);
    $this->assertNode('Project 1', OssSolutionInterface::class);
    $this->assertNode('Project 2', OssSolutionInterface::class);
  }

  /**
   * Tests fetching limited list with solutions from a GitLab platform.
   *
   * This covers gitlab.com code hosting provider but also any self-hosted
   * instance with limited projects.
   */
  public function testLimitedList(): void {
    $this->fetchSolutions('hosting_platform:gitlab_self_hosted_limited', 4);
    $this->assertNode('Project 1.1', OssSolutionInterface::class);
    $this->assertNode('Project 1.2', OssSolutionInterface::class);
    $this->assertNode('Project 2.1', OssSolutionInterface::class);
    $this->assertNode('Project 1', OssSolutionInterface::class);
  }

}
