<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Tool;

/**
 * Contract for a tool callable by Ollama via function-calling.
 *
 * Tool services are tagged 'ai_enhancement.tool' and auto-collected by the
 * ToolRegistry. To add a new tool, implement this interface, define the
 * service, and tag it — no further wiring needed.
 */
interface ToolInterface {

  /**
   * Stable identifier the model uses to invoke the tool.
   *
   * Snake_case, no spaces. Must be unique across all registered tools.
   */
  public function getName(): string;

  /**
   * JSON schema describing this tool's signature for the Ollama tools[] array.
   *
   * Must follow OpenAI/Ollama function-calling spec:
   *   [
   *     'type' => 'function',
   *     'function' => [
   *       'name' => '...',
   *       'description' => '...',
   *       'parameters' => [ ... JSON Schema ... ],
   *     ],
   *   ]
   */
  public function getSchema(): array;

  /**
   * Execute the tool. Arguments are already JSON-decoded.
   *
   * Return value is JSON-encoded by the caller before being sent back to the
   * model — so return arrays/scalars, not pre-serialized strings.
   *
   * @throws \Throwable on tool execution failure; the caller wraps and logs.
   */
  public function execute(array $arguments): mixed;

}
