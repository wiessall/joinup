<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\DevelopersItalia;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Selected project provider.
 */
abstract class SelectedProjectProvider extends DevelopersItalia {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Selected project which can be provided.
   *
   * @var string[]
   */
  protected array $selectedProjects = [
    'https://github.com/dummy-namespace-1/dummy-name-1',
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-1',
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    $provider = parent::create($container, $configuration, $pluginId, $pluginDefinition);
    $provider->setEntityManager($container->get('entity_type.manager'));

    return $provider;
  }

  /**
   * Sets entity manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   Entity manager.
   */
  protected function setEntityManager(EntityTypeManagerInterface $entityManager): void {
    $this->entityTypeManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    foreach (parent::fetch() as $project) {
      if (!in_array($project->url, $this->selectedProjects, TRUE)) {
        continue;
      }

      yield $project;
    }
  }

  /**
   * Checks if node was already called.
   *
   * @param string $gitUrl
   *   GIT Url.
   *
   * @return bool
   *   True if it was already called.
   */
  protected function wasCreated(string $gitUrl): bool {
    return $this->entityTypeManager->getStorage('node')
       ->getQuery()
       ->accessCheck(FALSE)
       ->condition('oss_git_url', $gitUrl)
       ->condition('type', 'oss_solution')
       ->count()
       ->execute() > 0;
  }

  /**
   * Makes publiccodeYml invalid.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   Project.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project
   *   Project with invalid YAML file.
   */
  protected function makeInvalid(Project $project): Project {
    $values = $project->toArray();
    $values['files']['publiccodeYml'] .= "\n   name: Project 1";

    return new Project(...$values);
  }

}
