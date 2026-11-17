<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Asks a local Ollama model about organisation names. Temperature 0, answers constrained by JSON schemas.
 *
 * Two questions:
 *   otherNames()  which names it knows the organisation by (English, original language, acronym, former names),
 *                 so a translated or renamed name can be looked up again
 *   choose()      which candidate, if any, the name refers to; it can only answer a candidate id or "none"
 * A failed call (unreachable server, unknown model, timeout, unreadable answer) throws a RuntimeException, so
 * the caller can tell it from a real "none" and must not remember it.
 */
final class LlmJudge {

  public function __construct(
    private readonly ClientInterface $http,
    private readonly string $baseUrl,
    private readonly string $model,
    private readonly string $kind = 'organisation',
    private readonly int $timeout = 120,
    private readonly ?bool $think = NULL,
  ) {}

  /**
   * "a country", "an organisation".
   */
  private function aKind(): string {
    return (preg_match('/^[aeiou]/i', $this->kind) ? 'an ' : 'a ') . $this->kind;
  }

  /**
   * Other names of the organisation, at most five; empty if the model does not know it.
   *
   * @return string[]
   */
  public function otherNames(string $input): array {
    $prompt = "A data import names {$this->aKind()} \"$input\" (possibly misspelt).\n"
      . "List the other names this {$this->kind} is known by: its name in English, its name in its own language, its "
      . "short and official names or code, and its current or former name if it was renamed. Only names you are sure "
      . "of. If it is not one {$this->kind}, or you do not know it, return an empty list.";
    $answer = $this->ask($prompt, [
      'type' => 'object',
      'properties' => ['names' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5]],
      'required' => ['names'],
    ]);
    $names = array_filter(array_map(fn($n) => is_string($n) ? trim($n) : '', (array) ($answer['names'] ?? [])));
    return array_values(array_diff(array_unique($names), [$input]));
  }

  /**
   * @param array<string, string> $candidates
   *   Candidate ids and their descriptions (name, acronym).
   * @param string[] $otherNames
   *   Names the organisation is also known by, as context.
   *
   * @return string|null
   *   The chosen candidate id, or NULL if the model answered "none".
   *
   * @throws \RuntimeException
   *   When the call fails.
   */
  public function choose(string $input, array $candidates, array $otherNames = []): ?string {
    if (!$candidates) {
      return NULL;
    }
    $ids = array_map('strval', array_keys($candidates));
    $list = implode("\n", array_map(fn($id, $label) => "- id \"$id\": $label", $ids, $candidates));
    $aka = $otherNames ? ' (also known as: ' . implode('; ', $otherNames) . ')' : '';
    $rules = $this->kind === 'organisation'
      ? "Name variants, translations, acronyms, legal suffixes (Ltd, Inc) and a later name after a renaming do not "
        . "make a different organisation. A country office, a department or a subsidiary of an organisation is a "
        . "different organisation, and so is one with the same name in another country."
      : "Spelling mistakes, translations, short and official names and a later name after a renaming do not make a "
        . "different {$this->kind}. A region, a group of several, or a neighbouring {$this->kind} with a similar name "
        . "is none of them.";
    $prompt = "A data import refers to {$this->aKind()} as \"$input\"$aka.\n"
      . "Which of these is it?\n$list\n\n"
      . "Answer with the id of the same {$this->kind}, or \"none\" if it is none of them. $rules When unsure, answer \"none\".";
    $answer = $this->ask($prompt, [
      'type' => 'object',
      'properties' => ['id' => ['type' => 'string', 'enum' => [...$ids, 'none']]],
      'required' => ['id'],
    ]);
    $id = (string) ($answer['id'] ?? '');
    // The schema should make this impossible, but a model server may not enforce it.
    return in_array($id, $ids, TRUE) ? $id : NULL;
  }

  /**
   * One structured chat call.
   *
   * @throws \RuntimeException
   *   When the server cannot be reached or does not answer with the requested JSON.
   */
  private function ask(string $prompt, array $schema): array {
    try {
      $response = $this->http->request('POST', rtrim($this->baseUrl, '/') . '/api/chat', [
        'json' => [
          'model' => $this->model,
          'messages' => [['role' => 'user', 'content' => $prompt]],
          'format' => $schema,
          'stream' => FALSE,
          'options' => ['temperature' => 0],
        ] + ($this->think === NULL ? [] : ['think' => $this->think]),
        'timeout' => $this->timeout,
      ]);
      $answer = json_decode((string) json_decode((string) $response->getBody(), TRUE)['message']['content'], TRUE);
    }
    catch (GuzzleException | \TypeError $e) {
      throw new \RuntimeException("LLM call to {$this->model} at {$this->baseUrl} failed: " . $e->getMessage(), 0, $e);
    }
    if (!is_array($answer)) {
      throw new \RuntimeException("LLM {$this->model} did not answer with JSON.");
    }
    return $answer;
  }

}
