<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Commands;

use Drupal\ai_enhancement\Entity\EnhancementProfileInterface;
use Drupal\ai_enhancement\Service\EnhancementPipeline;
use Drupal\ai_output_checks\Infrastructure\Drupal\QaParagraphManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for AI enhancement.
 *
 * Primary use case: run the pipeline synchronously on a single entity during
 * the spike, so you can watch the iterations and see the loop behaviour
 * without waiting for cron.
 */
class EnhancementCommands extends DrushCommands {

  public function __construct(
    private readonly EnhancementPipeline $pipeline,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QaParagraphManager $qaParagraphManager,
  ) {
    parent::__construct();
  }

  #[Command(name: 'ai-enhancement:run', aliases: ['aier'])]
  #[Argument(name: 'profile_id', description: 'Enhancement profile machine name.')]
  #[Argument(name: 'entity_id', description: 'Entity ID to enhance.')]
  #[Option(name: 'apply', description: 'If set, write the result back to the entity. Otherwise dry-run only.')]
  #[Usage(name: 'drush aier sti_project_enhancement 42', description: 'Dry-run on node 42.')]
  #[Usage(name: 'drush aier sti_project_enhancement 42 --apply', description: 'Run and write back.')]
  public function run(string $profile_id, string $entity_id, array $options = ['apply' => FALSE]): int {
    $profile = $this->entityTypeManager->getStorage('enhancement_profile')->load($profile_id);
    if (!$profile instanceof EnhancementProfileInterface) {
      $this->io()->error(sprintf('Profile not found: %s', $profile_id));
      return self::EXIT_FAILURE;
    }

    $entity = $this->entityTypeManager
      ->getStorage($profile->getEntityTypeTarget())
      ->load($entity_id);
    if (!$entity instanceof FieldableEntityInterface) {
      $this->io()->error(sprintf('Entity not found: %s:%s', $profile->getEntityTypeTarget(), $entity_id));
      return self::EXIT_FAILURE;
    }

    $this->io()->section(sprintf('Running %s on %s:%s', $profile_id, $profile->getEntityTypeTarget(), $entity_id));
    $result = $this->pipeline->run($entity, $profile);

    $this->io()->writeln(sprintf('Status: <info>%s</info>', $result->status));
    $this->io()->writeln(sprintf('Iterations: %d', $result->iterations));

    foreach ($result->history as $h) {
      $this->io()->section(sprintf('Iteration %d', $h['iteration']));
      $verdict = $h['verdict'] ?? [];
      $this->io()->writeln(sprintf('  approved: %s', !empty($verdict['approved']) ? 'yes' : 'no'));
      if (!empty($verdict['feedback'])) {
        $this->io()->writeln('  feedback: ' . $verdict['feedback']);
      }
      if (!empty($verdict['issues'])) {
        $this->io()->writeln('  issues:');
        foreach ($verdict['issues'] as $issue) {
          $this->io()->writeln('    - ' . $issue);
        }
      }
      $this->io()->writeln(sprintf('  tool_calls: %d', $h['tool_calls'] ?? 0));
    }

    $this->io()->section('Final content');
    $this->io()->writeln($result->content);

    if ($result->errorMessage) {
      $this->io()->warning($result->errorMessage);
    }

    if ($options['apply'] && $result->isApproved()) {
      $targetField = $profile->getTargetField();
      if ($targetField && $entity->hasField($targetField)) {
        $entity->set($targetField, $result->content);
      }
      $entity->save();

      if ($entity instanceof NodeInterface && $targetField) {
        $this->qaParagraphManager->setAiGenerated($entity, $targetField, TRUE);
      }
      $this->io()->success('Wrote back to entity (target field set, QA paragraph marked AI-generated).');
    }
    elseif ($options['apply']) {
      $this->io()->warning('Not approved — skipping write-back.');
    }

    return self::EXIT_SUCCESS;
  }

}
