<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface EnhancementProfileInterface extends ConfigEntityInterface {

  public function getEntityTypeTarget(): string;

  public function getBundle(): string;

  /** @return string[] */
  public function getSourceFields(): array;

  public function getTargetField(): string;

  public function getGeneratorModel(): string;

  public function getJudgeModel(): string;

  public function getGeneratorSystemPrompt(): string;

  public function getJudgeSystemPrompt(): string;

  public function getRefinementPromptTemplate(): string;

  public function getMaxIterations(): int;

  /** @return string[] */
  public function getEnabledTools(): array;

}
