<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_enhancement\Form\EnhancementProfileForm;

/**
 * Per-bundle enhancement configuration.
 *
 * One profile = one (entity_type, bundle) pair, defining which fields feed in,
 * which field gets written back, the prompts, the models, the regex patterns
 * for the deterministic step, and the tool whitelist.
 */
#[ConfigEntityType(
  id: 'enhancement_profile',
  label: new TranslatableMarkup('Enhancement profile'),
  label_singular: new TranslatableMarkup('enhancement profile'),
  label_plural: new TranslatableMarkup('enhancement profiles'),
  config_prefix: 'enhancement_profile',
  admin_permission: 'administer ai enhancement',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => 'Drupal\Core\Config\Entity\ConfigEntityListBuilder',
    'form' => [
      'add' => EnhancementProfileForm::class,
      'edit' => EnhancementProfileForm::class,
      'delete' => 'Drupal\Core\Entity\EntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider',
    ],
  ],
  links: [
    'collection' => '/admin/config/ai-enhancement/profiles',
    'add-form' => '/admin/config/ai-enhancement/profiles/add',
    'edit-form' => '/admin/config/ai-enhancement/profiles/{enhancement_profile}/edit',
    'delete-form' => '/admin/config/ai-enhancement/profiles/{enhancement_profile}/delete',
  ],
  config_export: [
    'id',
    'label',
    'entity_type',
    'bundle',
    'source_fields',
    'target_field',
    'generator_model',
    'judge_model',
    'generator_system_prompt',
    'judge_system_prompt',
    'refinement_prompt_template',
    'max_iterations',
    'enabled_tools',
  ],
)]
class EnhancementProfile extends ConfigEntityBase implements EnhancementProfileInterface {

  protected string $id;
  protected string $label;
  protected string $entity_type = 'node';
  protected string $bundle = '';
  protected array $source_fields = [];
  protected string $target_field = '';
  protected string $generator_model = 'llama3.1:8b';
  protected string $judge_model = 'llama3.1:8b';
  protected string $generator_system_prompt = '';
  protected string $judge_system_prompt = '';
  protected string $refinement_prompt_template = '';
  protected int $max_iterations = 3;
  protected array $enabled_tools = ['taxonomy_lookup'];

  public function getEntityTypeTarget(): string { return $this->entity_type; }
  public function getBundle(): string { return $this->bundle; }
  public function getSourceFields(): array { return $this->source_fields; }
  public function getTargetField(): string { return $this->target_field; }
  public function getGeneratorModel(): string { return $this->generator_model; }
  public function getJudgeModel(): string { return $this->judge_model; }
  public function getGeneratorSystemPrompt(): string { return $this->generator_system_prompt; }
  public function getJudgeSystemPrompt(): string { return $this->judge_system_prompt; }
  public function getRefinementPromptTemplate(): string { return $this->refinement_prompt_template; }
  public function getMaxIterations(): int { return $this->max_iterations; }
  public function getEnabledTools(): array { return $this->enabled_tools; }

}
