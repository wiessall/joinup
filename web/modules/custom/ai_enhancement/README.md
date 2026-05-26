# AI Enhancement

LLM-driven enhancement pipeline that integrates with the existing `ai_output_checks` module. Implements the team's two-gate workflow: deterministic check first, then LLM judge, looping back to the generator on either gate failure.

## Pipeline

```
   ┌──────────────────────┐
   │  Entity insert/update│
   └──────────┬───────────┘
              │
              ▼
   ┌──────────────────────┐
   │  Match profiles      │  ──► one job per matching profile
   │  for type+bundle     │       enqueued in ai_enhancement_worker
   └──────────┬───────────┘
              │  (async on cron, or `drush queue:run`)
              ▼
   ┌──────────────────────────────────────────────────────────────────┐
   │  EnhancementPipeline::run()                                      │
   │                                                                  │
   │  loop (up to max_iterations):                                    │
   │                                                                  │
   │    1. Enrichment ─ generator LLM (with tools)                    │
   │    2. Core annotation filled — draft ready                       │
   │    3. Deterministic check (ai_output_checks: CheckRunner)        │
   │                                                                  │
   │    4. ◆ Gate 1: report.isOk()?                                   │
   │           NO  → loop back with findings + refinement prompt      │
   │           YES → continue                                         │
   │                                                                  │
   │    5. LLM Judge (Ollama, structured JSON via format constraint)  │
   │                                                                  │
   │    6. ◆ Gate 2: verdict.approved?                                │
   │           NO  → loop back with feedback + refinement prompt      │
   │           YES → break                                            │
   │                                                                  │
   │  → EnhancementResult { status, content, iterations, history }    │
   └──────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼ (status == approved)
   ┌──────────────────────────────────────────────────────────────────┐
   │  Write back                                                      │
   │    - target_field ← content                                      │
   │    - QaParagraphManager::setAiGenerated(node, target_field, TRUE)│
   │    - NodeAudit::appendLog appended each iteration                │
   └──────────────────────────────────────────────────────────────────┘
```

## How it integrates with `ai_output_checks`

This module is intentionally narrow: **it does no checking itself**. All deterministic checks are delegated to `ai_output_checks`. Specifically:

| Need | Service used |
|------|--------------|
| Run all regex/URL/duplicate/etc. checks | `ai_output_checks.check_runner` |
| Report value object the LLM judge consumes | `ai_output_checks\Domain\ValueObject\CheckReport` |
| Mark a field as AI-generated after approval | `ai_output_checks.qa_paragraph_manager::setAiGenerated()` |
| Audit log per iteration | `ai_output_checks.node_audit::appendLog()` |

**Consequence:** to add a new check (e.g. length guard, profanity filter, duplicate detector), implement `AiCheckInterface` in `ai_output_checks` as documented in that module's README. The new check automatically runs in this module's pipeline — no changes here.

**Consequence:** AI marking lives in the QA paragraph (`field_quality_assessment`), not in a sibling "status field". This matches what `MarkFieldAiGenerated` and `CheckContentRegexp` already do.

## Why two gates

The flowchart's two-gate design is meaningfully different from a one-gate "always send report to judge" loop:

1. **Failed deterministic checks short-circuit before the judge call.** No point paying for an LLM round-trip to evaluate a draft that already failed regex/URL checks.
2. **The judge gets cleaner inputs.** It only sees drafts that passed basic validation, so its feedback focuses on what it's actually qualified to assess — coherence, completeness, factuality — rather than mechanical issues.
3. **Refinement prompts differ by gate.** Gate-1 failures get a terse, fix-the-specific-issue prompt with concrete findings. Gate-2 failures get a prompt that foregrounds the judge's qualitative feedback. See `buildDeterministicRefinementPrompt()` vs `buildJudgeRefinementPrompt()`.

## Requirements

- Drupal 10.2+ or 11
- PHP 8.1+
- `ai_output_checks` module (this module declares a hard dependency)
- Ollama running locally (default: `http://localhost:11434`), with at least one model that supports tool-calling (e.g. `llama3.1:8b`, `qwen2.5`, `mistral-nemo`)
- Ollama 0.5+ for the judge's structured-output `format` schema

## Install

1. Drop the `ai_enhancement` directory into `web/modules/custom/`.
2. `drush en ai_enhancement` (will pull `ai_output_checks` in as a dep).
3. Visit `/admin/config/ai-enhancement/settings` to confirm the Ollama URL.
4. Visit `/admin/config/ai-enhancement/profiles` to create your first profile.

## Creating a profile

Each profile binds one `(entity_type, bundle)` to a generator/judge pair, source/target fields, prompts, and a tool whitelist.

Example for an STI project node:

| Field | Value |
|-------|-------|
| Entity type | `node` |
| Bundle | `sti_project` |
| Source fields | `title`<br>`body`<br>`field_description` |
| Target field | `field_ai_annotation` |
| Generator model | `llama3.1:8b` |
| Judge model | `llama3.1:8b` |
| Max iterations | `3` |
| Enabled tools | `taxonomy_lookup` |

The judge system prompt should explicitly tell the model to return JSON matching `{approved: bool, feedback: string, issues: string[]}`. The `format` constraint enforces the shape; the prompt explains the meaning.

**Note:** there's no "regex patterns" field on the profile any more. Patterns live in the existing `ai_output_checks` config and apply across all profiles via the tagged-check pipeline.

## Trying it out

```bash
# Dry-run (does not modify the entity, prints each iteration)
drush ai-enhancement:run sti_project_enhancement 42

# Run and write back
drush ai-enhancement:run sti_project_enhancement 42 --apply
```

The Drush command prints each iteration's outcome (`deterministic_failed` / `judge_rejected` / `approved`) so you can see which gate is bouncing your drafts.

For production use, jobs are queued on entity save and processed on cron. To process the queue immediately:

```bash
drush queue:run ai_enhancement_worker
```

## Adding a new check

Do it in `ai_output_checks`:

1. Create a class implementing `Drupal\ai_output_checks\Domain\Checker\AiCheckInterface`.
2. Register it as a tagged service: `tags: [ { name: ai_output_checks.check } ]`.
3. Implement `supports($fieldName, $fieldType)` to scope when it runs.

Your check runs in this module's pipeline automatically. No code in `ai_enhancement` needs to change.

## Adding a new generator tool

Tools are function-calling capabilities the generator LLM can invoke during enrichment (e.g. taxonomy lookup). They're distinct from deterministic checks.

1. Implement `Drupal\ai_enhancement\Tool\ToolInterface`.
2. Register it as a tagged service: `tags: [ { name: ai_enhancement.tool } ]`.
3. Add its name to `enabled_tools` on any profile that should use it.

## Notes on the existing `ai_output_checks` module

A few cleanup suggestions (optional, none break anything):

- `src/Plugin/Action/MarkFieldAiGenerated_backup.php` is an exact duplicate of `MarkFieldAiGenerated.php`. Delete the backup.
- `MarkFieldAiGeneratedECA.php` (the ECA-specific variant) becomes redundant once ECA-driven enhancement is decommissioned in favour of this module's queue-driven flow. Keep both during transition; remove the ECA variant once cutover is complete.
- The README TODO about `CheckContentRegexp` performing node saves inside `getOrCreate` / `syncParagraphRevisionOnNode` still applies. Not a blocker for integration.

No structural changes to `ai_output_checks` are required. The integration is purely additive.

## Backlog

**Phase 1 (production shape):**
- Autocomplete in the admin form for entity types, bundles, field names
- Model picker that queries Ollama `/api/tags` for installed models
- Bulk re-enhancement Drush command for back-filling historical content
- Make the queue worker resilient: retry on transient Ollama errors with backoff

**Phase 2 (operational):**
- Per-iteration logging viewer in admin UI
- A/B comparison view — generator-only output vs full pipeline output
- Eval harness: regression-test prompts against a labeled "golden dataset" (per the team's sticky note in the flow diagram)
- Convert `FuzzyOrgMatch` in `ai_output_checks` into a tagged `AiCheckInterface` so duplicate detection runs in the same pipeline (already TODO'd in that module's README)
