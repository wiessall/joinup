<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Service;

use Drupal\ai_enhancement\Exception\OllamaException;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Wraps the Ollama /api/chat endpoint.
 *
 * The chat() method handles tool-calling internally: if the model returns
 * tool_calls, this class executes them via the ToolRegistry, appends the
 * results as 'tool' messages, and re-calls Ollama — until the model returns
 * a plain content message or the inner loop cap is hit.
 *
 * Callers therefore only see the final assistant message and do not need
 * to implement the tool-call protocol themselves.
 */
class OllamaClient {

  public function __construct(
    private readonly ClientInterface $http,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
    private readonly ToolRegistry $toolRegistry,
  ) {}

  /**
   * Send a chat request, transparently handling any tool-call rounds.
   *
   * @param string $model
   *   Ollama model identifier (e.g. 'llama3.1:8b').
   * @param array $messages
   *   Initial message list. Each: ['role' => ..., 'content' => ...].
   * @param string[] $enabledTools
   *   Tool names the model may call this turn. Empty = no tools.
   * @param array|null $jsonSchema
   *   Optional JSON Schema. When set, Ollama constrains output to it via the
   *   'format' parameter and the returned content is the parsed JSON object.
   *   NOTE: structured output and tool-calling don't combine well — Ollama
   *   may ignore tools when format is set. Use one or the other per call.
   * @param array $options
   *   Optional Ollama options block (temperature, num_ctx, etc.).
   *
   * @return array
   *   ['content' => string|array, 'raw_messages' => array, 'tool_calls_made' => int]
   *
   * @throws OllamaException
   */
  public function chat(
    string $model,
    array $messages,
    array $enabledTools = [],
    ?array $jsonSchema = NULL,
    array $options = [],
  ): array {
    $settings = $this->configFactory->get('ai_enhancement.settings');
    $maxLoopIterations = (int) ($settings->get('max_tool_loop_iterations') ?: 6);

    $toolSchemas = $enabledTools ? $this->toolRegistry->getSchemasForEnabled($enabledTools) : [];
    $toolCallsMade = 0;
    $workingMessages = $messages;

    for ($i = 0; $i < $maxLoopIterations; $i++) {
      $response = $this->postChat($model, $workingMessages, $toolSchemas, $jsonSchema, $options);
      $assistant = $response['message'] ?? [];

      // Always record the assistant turn — including any tool_calls, so the
      // conversation history is complete and re-playable.
      $workingMessages[] = $assistant;

      $toolCalls = $assistant['tool_calls'] ?? [];

      if (empty($toolCalls)) {
        // Final answer reached.
        $content = $assistant['content'] ?? '';
        if ($jsonSchema !== NULL && is_string($content)) {
          $decoded = json_decode($content, TRUE);
          if (json_last_error() !== JSON_ERROR_NONE) {
            throw new OllamaException(sprintf(
              'Ollama returned non-JSON content despite format constraint: %s',
              json_last_error_msg()
            ));
          }
          $content = $decoded;
        }
        return [
          'content' => $content,
          'raw_messages' => $workingMessages,
          'tool_calls_made' => $toolCallsMade,
        ];
      }

      // Execute each tool call and append its result as a 'tool' message.
      foreach ($toolCalls as $call) {
        $toolCallsMade++;
        $function = $call['function'] ?? [];
        $name = $function['name'] ?? '';
        $args = $function['arguments'] ?? [];
        // Ollama sometimes returns arguments as a JSON string rather than object.
        if (is_string($args)) {
          $decoded = json_decode($args, TRUE);
          $args = is_array($decoded) ? $decoded : [];
        }

        try {
          $result = $this->toolRegistry->execute($name, $args);
          $resultJson = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        catch (\Throwable $e) {
          $this->logger->warning('Tool @name failed: @msg', ['@name' => $name, '@msg' => $e->getMessage()]);
          $resultJson = json_encode(['error' => $e->getMessage()]);
        }

        $workingMessages[] = [
          'role' => 'tool',
          'tool_name' => $name,
          'content' => $resultJson,
        ];
      }
    }

    throw new OllamaException(sprintf(
      'Tool-call loop exceeded %d iterations without final answer (model: %s).',
      $maxLoopIterations,
      $model
    ));
  }

  /**
   * Single non-streaming POST to /api/chat.
   */
  private function postChat(
    string $model,
    array $messages,
    array $toolSchemas,
    ?array $jsonSchema,
    array $options,
  ): array {
    $settings = $this->configFactory->get('ai_enhancement.settings');
    $base = rtrim((string) ($settings->get('ollama_base_url') ?: 'http://localhost:11434'), '/');
    $timeout = (int) ($settings->get('request_timeout') ?: 120);

    $payload = [
      'model' => $model,
      'messages' => $messages,
      'stream' => FALSE,
    ];
    if (!empty($toolSchemas)) {
      $payload['tools'] = $toolSchemas;
    }
    if ($jsonSchema !== NULL) {
      $payload['format'] = $jsonSchema;
    }
    if (!empty($options)) {
      $payload['options'] = $options;
    }

    try {
      $response = $this->http->request('POST', $base . '/api/chat', [
        'json' => $payload,
        'timeout' => $timeout,
        'headers' => ['Content-Type' => 'application/json'],
      ]);
    }
    catch (GuzzleException $e) {
      throw new OllamaException(sprintf('Ollama HTTP error: %s', $e->getMessage()), 0, $e);
    }

    $body = (string) $response->getBody();
    $decoded = json_decode($body, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
      throw new OllamaException(sprintf('Ollama returned malformed JSON: %s', json_last_error_msg()));
    }

    if (isset($decoded['error'])) {
      throw new OllamaException(sprintf('Ollama error: %s', $decoded['error']));
    }

    return $decoded;
  }

}
