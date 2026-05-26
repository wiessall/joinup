<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Service;

use Drupal\ai_enhancement\EnhancementResult;
use Drupal\ai_enhancement\Entity\EnhancementProfileInterface;
use Drupal\ai_enhancement\Exception\OllamaException;
use Drupal\ai_output_checks\Application\Runner\CheckRunner;
use Drupal\ai_output_checks\Domain\ValueObject\CheckReport;
use Drupal\ai_output_checks\Domain\ValueObject\Finding;
use Drupal\ai_output_checks\Infrastructure\Drupal\NodeAudit;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * The enhancement loop — matches the team workflow diagram exactly:
 *
 *   1. Enrichment (generator LLM, may use tools)
 *   2. Core annotation filled (draft ready)
 *   3. Deterministic check & report  (ai_output_checks: CheckRunner)
 *   4. QA good? ── NO ──► back to 1 with report + refinement prompt
 *      │ YES
 *      ▼
 *   5. LLM Judge (structured verdict via Ollama format constraint)
 *   6. QA good? ── NO ──► back to 1 with verdict + refinement prompt
 *      │ YES
 *      ▼
 *   7. Save (queue worker write-back, fields marked AI via QaParagraphManager)
 *
 * Two gates rather than one — failed deterministic checks short-circuit
 * before the judge call, saving an LLM round-trip and giving cleaner inputs
 * when the judge does run.
 */
class EnhancementPipeline {

  public function __construct(
    private readonly OllamaClient $ollama,
    private readonly ToolRegistry $tools,
    private readonly CheckRunner $checkRunner,
    private readonly NodeAudit $nodeAudit,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  public function run(FieldableEntityInterface $entity, EnhancementProfileInterface $profile): EnhancementResult {
    $history = [];
    $draftContent = '';
    $maxIterations = max(1, $profile->getMaxIterations());
    $targetField = $profile->getTargetField();
    $targetFieldType = $this->resolveFieldType($entity, $targetField);

    // Generator's running thread — reused across iterations so the model sees
    // its prior attempts and the gate feedback in context.
    $generatorMessages = [
      ['role' => 'system', 'content' => $profile->getGeneratorSystemPrompt()],
      ['role' => 'user', 'content' => $this->buildInitialUserPrompt($entity, $profile)],
    ];

    for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
      try {
        // ── Step 1+2: Enrichment → draft ──────────────────────────────────
        $generated = $this->ollama->chat(
          model: $profile->getGeneratorModel(),
          messages: $generatorMessages,
          enabledTools: $profile->getEnabledTools(),
        );
        $draftContent = is_string($generated['content']) ? $generated['content'] : json_encode($generated['content']);
        $generatorMessages = $generated['raw_messages'];

        // ── Step 3: Deterministic check & report ──────────────────────────
        $report = $this->checkRunner->run($targetField, $targetFieldType, [$draftContent]);

        // ── Step 4: First gate ────────────────────────────────────────────
        if (!$report->isOk()) {
          $history[] = $this->recordIteration($iteration, $draftContent, $report, NULL, 'deterministic_failed', $generated['tool_calls_made']);
          $this->auditIfNode($entity, sprintf('iteration %d failed deterministic check (%d findings)', $iteration, count($report->findings())));
          $generatorMessages[] = [
            'role' => 'user',
            'content' => $this->buildDeterministicRefinementPrompt($report, $profile),
          ];
          continue;
        }

        // ── Step 5: LLM Judge ─────────────────────────────────────────────
        $verdict = $this->callJudge($profile, $draftContent, $report);

        // ── Step 6: Second gate ───────────────────────────────────────────
        if (empty($verdict['approved'])) {
          $history[] = $this->recordIteration($iteration, $draftContent, $report, $verdict, 'judge_rejected', $generated['tool_calls_made']);
          $this->auditIfNode($entity, sprintf('iteration %d rejected by judge: %s', $iteration, mb_strimwidth((string) ($verdict['feedback'] ?? ''), 0, 200, '…')));
          $generatorMessages[] = [
            'role' => 'user',
            'content' => $this->buildJudgeRefinementPrompt($verdict, $report, $profile),
          ];
          continue;
        }

        // ── Step 7: Approved — done ───────────────────────────────────────
        $history[] = $this->recordIteration($iteration, $draftContent, $report, $verdict, 'approved', $generated['tool_calls_made']);
        $this->auditIfNode($entity, sprintf('approved after %d iteration(s)', $iteration));

        return new EnhancementResult(
          status: 'approved',
          content: $draftContent,
          iterations: $iteration,
          history: $history,
        );
      }
      catch (OllamaException $e) {
        $this->logger->error('Pipeline failed at iteration @i: @msg', ['@i' => $iteration, '@msg' => $e->getMessage()]);
        $this->auditIfNode($entity, sprintf('error at iteration %d: %s', $iteration, $e->getMessage()));
        return new EnhancementResult(
          status: 'error',
          content: $draftContent,
          iterations: $iteration,
          history: $history,
          errorMessage: $e->getMessage(),
        );
      }
    }

    $this->auditIfNode($entity, sprintf('exhausted %d iterations without approval', $maxIterations));
    return new EnhancementResult(
      status: 'max_iterations',
      content: $draftContent,
      iterations: $maxIterations,
      history: $history,
    );
  }

  /**
   * Resolve the target field's data type for CheckRunner.
   *
   * 'title' is treated as 'string' to match CheckContentRegexp's convention
   * in the existing ai_output_checks Action plugin.
   */
  private function resolveFieldType(FieldableEntityInterface $entity, string $fieldName): string {
    if ($fieldName === 'title') {
      return 'string';
    }
    if ($entity->hasField($fieldName)) {
      return $entity->get($fieldName)->getFieldDefinition()->getType();
    }
    return 'text_long';
  }

  private function buildInitialUserPrompt(FieldableEntityInterface $entity, EnhancementProfileInterface $profile): string {
    $sections = [];
    foreach ($profile->getSourceFields() as $fieldName) {
      if (!$entity->hasField($fieldName)) {
        continue;
      }
      $value = $entity->get($fieldName)->getString();
      if ($value !== '') {
        $sections[] = sprintf('## %s%s%s', $fieldName, PHP_EOL, $value);
      }
    }
    $entityLabel = method_exists($entity, 'label') ? (string) $entity->label() : '';
    $header = sprintf("Entity: %s (id: %s)", $entityLabel, $entity->id());
    return $header . PHP_EOL . PHP_EOL . implode(PHP_EOL . PHP_EOL, $sections);
  }

  /**
   * Judge call — constrained JSON output via Ollama's `format` parameter.
   */
  private function callJudge(EnhancementProfileInterface $profile, string $draft, CheckReport $report): array {
    $messages = [
      ['role' => 'system', 'content' => $profile->getJudgeSystemPrompt()],
      ['role' => 'user', 'content' => sprintf(
        "DRAFT TO REVIEW:%s%s%s%sDETERMINISTIC CHECK REPORT:%s%s",
        PHP_EOL, $draft, PHP_EOL, PHP_EOL,
        PHP_EOL, $this->renderReportForPrompt($report)
      )],
    ];

    $verdictSchema = [
      'type' => 'object',
      'properties' => [
        'approved' => ['type' => 'boolean'],
        'feedback' => ['type' => 'string'],
        'issues' => [
          'type' => 'array',
          'items' => ['type' => 'string'],
        ],
      ],
      'required' => ['approved', 'feedback'],
    ];

    $response = $this->ollama->chat(
      model: $profile->getJudgeModel(),
      messages: $messages,
      enabledTools: [],
      jsonSchema: $verdictSchema,
    );

    $verdict = $response['content'];
    if (!is_array($verdict)) {
      throw new OllamaException('Judge did not return a JSON object.');
    }
    return $verdict;
  }

  /**
   * Refinement prompt for gate 1 (deterministic) failures.
   *
   * Findings are concrete and machine-extracted — the model can fix them
   * directly. Tends to converge in 1-2 extra iterations.
   */
  private function buildDeterministicRefinementPrompt(CheckReport $report, EnhancementProfileInterface $profile): string {
    $template = $profile->getRefinementPromptTemplate();
    $reportText = $this->renderReportForPrompt($report);

    if ($template === '') {
      return sprintf(
        "Your draft failed automated quality checks. The following issues must be fixed:%s%s%sPlease produce a revised version that resolves these issues. Do not change anything that wasn't flagged.",
        PHP_EOL . PHP_EOL,
        $reportText,
        PHP_EOL . PHP_EOL,
      );
    }

    return strtr($template, [
      '{feedback}' => 'Automated check failed.',
      '{issues}' => $this->renderFindingsAsBullets($report),
      '{report}' => $reportText,
    ]);
  }

  /**
   * Refinement prompt for gate 2 (judge) failures.
   *
   * Judge feedback is qualitative — coherence, completeness, tone — so the
   * prompt foregrounds the feedback string.
   */
  private function buildJudgeRefinementPrompt(array $verdict, CheckReport $report, EnhancementProfileInterface $profile): string {
    $template = $profile->getRefinementPromptTemplate();
    $issues = $verdict['issues'] ?? [];

    if ($template === '') {
      return sprintf(
        "The reviewer requested changes:%s%s%s%sIssues to address:%s%s%s%sThe automated check passed; keep it passing in your revision.",
        PHP_EOL . PHP_EOL,
        (string) ($verdict['feedback'] ?? ''),
        PHP_EOL, PHP_EOL,
        PHP_EOL,
        $issues ? '- ' . implode(PHP_EOL . '- ', $issues) : '(none listed)',
        PHP_EOL, PHP_EOL,
      );
    }

    return strtr($template, [
      '{feedback}' => (string) ($verdict['feedback'] ?? ''),
      '{issues}' => $issues ? '- ' . implode(PHP_EOL . '- ', $issues) : '(none listed)',
      '{report}' => $this->renderReportForPrompt($report),
    ]);
  }

  private function renderReportForPrompt(CheckReport $report): string {
    if ($report->isOk()) {
      return '(no issues found)';
    }
    $lines = [];
    foreach ($report->findings() as $finding) {
      $lines[] = $this->renderFinding($finding);
    }
    return implode(PHP_EOL, $lines);
  }

  private function renderFindingsAsBullets(CheckReport $report): string {
    if ($report->isOk()) {
      return '(none)';
    }
    $lines = [];
    foreach ($report->findings() as $finding) {
      $lines[] = '- ' . $this->renderFinding($finding);
    }
    return implode(PHP_EOL, $lines);
  }

  private function renderFinding(Finding $f): string {
    $parts = [sprintf('[%s/%s] %s', $f->checkId, $f->severity, $f->message)];
    if ($f->evidence !== '') {
      $parts[] = sprintf('evidence: %s', $f->evidence);
    }
    return implode(' — ', $parts);
  }

  private function recordIteration(int $iteration, string $draft, CheckReport $report, ?array $verdict, string $outcome, int $toolCalls): array {
    return [
      'iteration' => $iteration,
      'draft_excerpt' => mb_substr($draft, 0, 240),
      'outcome' => $outcome,
      'findings' => count($report->findings()),
      'report' => $this->serializeReport($report),
      'verdict' => $verdict,
      'tool_calls' => $toolCalls,
    ];
  }

  private function serializeReport(CheckReport $report): array {
    $out = ['field' => $report->fieldName, 'ok' => $report->isOk(), 'findings' => []];
    foreach ($report->findings() as $f) {
      $out['findings'][] = [
        'check_id' => $f->checkId,
        'severity' => $f->severity,
        'message' => $f->message,
        'evidence' => $f->evidence,
      ];
    }
    return $out;
  }

  /**
   * Append to the node's field_logs if the entity is a node.
   * QaParagraphManager and NodeAudit are node-specific by design.
   */
  private function auditIfNode(FieldableEntityInterface $entity, string $message): void {
    if ($entity instanceof NodeInterface) {
      $this->nodeAudit->appendLog($entity, 'ai_enhancement', $message);
    }
  }

}
