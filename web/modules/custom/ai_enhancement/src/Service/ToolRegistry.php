<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Service;

use Drupal\ai_enhancement\Tool\ToolInterface;
use Psr\Log\LoggerInterface;

/**
 * Collects tagged tool services and dispatches calls by name.
 *
 * Tools are collected via the 'service_collector' tag system: any service
 * tagged 'ai_enhancement.tool' is passed to addTool() at container build time.
 */
class ToolRegistry {

  /** @var array<string, ToolInterface> */
  private array $tools = [];

  public function __construct(
    private readonly LoggerInterface $logger,
  ) {}

  public function addTool(ToolInterface $tool): void {
    $name = $tool->getName();
    if (isset($this->tools[$name])) {
      $this->logger->warning('Tool @name registered twice; later one wins.', ['@name' => $name]);
    }
    $this->tools[$name] = $tool;
  }

  /**
   * Returns Ollama-formatted schemas only for the requested subset.
   *
   * @param string[] $enabled
   *   Tool names to include. Empty array → no tools (model gets no function-call options).
   *
   * @return array
   *   Array of tool schema entries suitable for the Ollama tools[] parameter.
   */
  public function getSchemasForEnabled(array $enabled): array {
    $schemas = [];
    foreach ($enabled as $name) {
      if (isset($this->tools[$name])) {
        $schemas[] = $this->tools[$name]->getSchema();
      }
      else {
        $this->logger->warning('Enabled tool @name not found in registry.', ['@name' => $name]);
      }
    }
    return $schemas;
  }

  /**
   * Invoke a tool by name. Caller catches and handles exceptions.
   *
   * @throws \InvalidArgumentException if the tool name is unknown.
   */
  public function execute(string $name, array $arguments): mixed {
    if (!isset($this->tools[$name])) {
      throw new \InvalidArgumentException(sprintf('Unknown tool: %s', $name));
    }
    return $this->tools[$name]->execute($arguments);
  }

  public function has(string $name): bool {
    return isset($this->tools[$name]);
  }

  /** @return string[] */
  public function getAvailableToolNames(): array {
    return array_keys($this->tools);
  }

}
