# AI Output Checks module architecture

This module is built around a simple idea:

**ECA triggers an Action plugin → the Action calls services → services run checks and update QA/log state on the node.**

The code is structured so that:
- ECA workflows stay small (thin Action plugins).
- Adding checks is easy (plug-in-like via service tags).
- Most logic is testable without heavy Drupal bootstrapping.

---

## Folder purposes

### `src/Plugin/Action/`
**Purpose:** Drupal “entry points”.

These are Action plugins (invoked by ECA or Drupal Actions). They should be thin:
- read the node/entity from `$entity`
- call one or two services
- log what happened

**Rule:** If an Action grows, logic belongs in `Application/` or `Infrastructure/`.

---

### `src/Application/`
**Purpose:** Orchestration / “use-cases”.

This layer coordinates the pipeline:
- which checks run
- how results are merged
- what outcomes mean (later: policy/enforcement/remediation planning)

It can know about the pipeline, but should avoid direct heavy entity CRUD (that belongs in Infrastructure).

---

### `src/Domain/`
**Purpose:** Check logic + stable result data structures.

This is the most testable layer:
- check interface contracts (`AiCheckInterface`)
- result objects (`Finding`, `CheckReport`)
- pure algorithms (profanity/length/etc. later)

**Rule:** Avoid `\Drupal::*` here. Prefer pure inputs → pure outputs.

---

### `src/Infrastructure/Drupal/`
**Purpose:** “Drupal glue” services.

This is where entity storage and Field/Paragraph concerns live:
- create/load QA paragraph
- keep paragraph revision references correct (`entity_reference_revisions`)
- saving entities safely

Usually tested with **Kernel tests**.

---

## What each file does (in → out logic)

### `src/Infrastructure/Drupal/QaParagraphManager.php`
**Job:** Manage QA paragraphs attached to a node.

**Inputs:**
- `NodeInterface $node`
- `string $fieldMachineName`
- `bool $flag` (for ai_generated)

**Outputs:**
- `Paragraph|null` (when returning QA paragraph)
- `bool` (for isAiGenerated)
- side effects: may save paragraph and sometimes node (to update revision ids)

**In → Out logic:**
1. Find QA paragraph for `$fieldMachineName` among the node’s QA references.
2. If missing: create paragraph + save it + attach it to node with `target_revision_id` + save node.
3. Update fields like `field_ai_generated`, save paragraph, update node reference to new revision id.

---
### `src/Infrastructure/Drupal/NodeAudit.php`
**Job:** Manage QA fields attached of a node.

**Inputs:**
- `NodeInterface $node`
- `string $event`
- `string $message` 

**Outputs:**
- `void` will write standardized data change updates to the node's `field_logs` field; sets `field_state` and `field_record_status`

**In → Out logic:**
1. Format a log message using event and message
2. Optional: Sets `Needs review` as `field_state`
3. Optional: Sets `Rejected - Bad AI generated` as `field_record_status`

---

### `src/Domain/ValueObject/Finding.php`
**Job:** Represent one problem discovered by a check.

**Inputs:**
- `checkId`, `severity`, `message`, `evidence`, `data`

**Outputs:**
- immutable “record” object

---

### `src/Domain/ValueObject/CheckReport.php`
**Job:** Collect all findings for one field.

**Inputs:**
- field name
- Findings added over time

**Outputs:**
- `findings(): Finding[]`
- `isOk(): bool`

**In → Out logic:**
- If no findings were added → PASS for that field.
- Else → FAIL for that field.

---

### `src/Domain/Checker/AiCheckInterface.php`
**Job:** Contract for “a check”.

**Inputs:**
- field name + field type (Phase 1)
- string values array

**Outputs:**
- `CheckReport`

---

### `src/Application/Runner/CheckRunner.php`
**Job:** Run all registered checks and merge their findings.

**Inputs:**
- `fieldName`, `fieldType`, `values[]`

**Outputs:**
- merged `CheckReport`

**In → Out logic:**
1. Create empty `CheckReport`.
2. Iterate all tagged checks:
   - if `supports(...)` false: skip
   - else run `check(...)` and merge findings
3. Return merged report.

---

### `src/Domain/Checker/RegexpGuardCheck.php`
**Job:** Wrapper adapting `RegexpChecker::check(string): array` to `AiCheckInterface`.

**Inputs:**
- values strings

**Outputs:**
- `CheckReport` with a Finding for each regex hit

**In → Out logic:**
1. For each value:
2. Call `RegexpChecker->check(value)` → `hits[]` (each hit includes reason/pattern/match)
3. For each hit → create Finding(checkId=regexp_guard, severity=error, evidence=match, data=hit)
4. Return report.

---

### `src/Domain/Checker/UrlGuardCheck.php`
**Job:** Wrapper adapting `UrlChecker::check(string): UrlCheckResult` to `AiCheckInterface`.

**Inputs:**
- values strings

**Outputs:**
- `CheckReport` with findings for invalid URLs

**In → Out logic:**
1. For each value:
2. If it looks like a URL: normalize (`www.` → `https://`)
3. Call `UrlChecker->check(url)`
4. If not ok → add Finding(checkId=url_guard, severity=error, message=reason, data=httpStatus/normalized)
5. Return report.

---

### `src/Plugin/Action/CheckContentRegexp.php`
**Job:** ECA Action to validate selected fields on a node using the pipeline.

**Inputs:**
- `$entity` = Node passed by ECA

**Outputs:**
- side effects: clear fields on failure, set QA ai_generated on success, log

**In → Out logic (per field):**
1. Choose fields to validate:
   - either “AI-marked only” (from QA)
   - or discover eligible fields (title + string/text/link)
2. Extract values for the field.
3. Determine field type (`title` treated as `string`).
4. `CheckRunner->run(fieldName, fieldType, values)` → report.
5. If report ok:
   - `QaParagraphManager->setAiGenerated(node, fieldName, TRUE)`
6. Else:
   - clear the field
   - log findings
   - (optional) delete node if title failed AND title QA says AI-generated

---

### `src/Plugin/Action/FuzzyOrgMatch.php`
**Job:** Specialized Action checking org name fields on innovation nodes for duplicates.

**Inputs:**
- innovation node

**Outputs:**
- logs duplicate matches
- sets QA paragraph term “Duplicate” (current behavior)

**In → Out logic:**
1. For each org field, for each org name string:
2. Compare against existing organization nodes using DuplicateDetector.
3. If duplicates exist:
   - mark QA paragraph for that field as “Duplicate”
   - log details

*(Later this becomes an `AiCheckInterface` check service so it runs inside the same pipeline.)*

---

## General architecture idea

### The pipeline concept
- Checks are independent components that take strings and output Findings.
- `CheckRunner` collects checks via service tags and runs all applicable ones.
- Actions do not hardcode checks.

### Why this structure
- **Extendability:** add new checks without editing Actions/ECA.
- **Testability:** Domain checks are pure and unit-testable.
- **Maintainability:** Drupal entity operations are centralized in Infrastructure.

### How Phase 2 fits later
Phase 2 introduces:
- a `Policy` layer (which checks apply, severity thresholds)
- an `EnforcementService` (clear, flag, remediate, request revision)
So Actions become even thinner.

### How LLM revision loops fit later
Because Findings are structured:
- build prompts from (content + findings)
- track attempts per field/node (QA paragraph state)
- stop after N attempts
- write audit history into the node’s log paragraph

---

# Contributor guide

## How to add a new check

### 1) Create a new check class
Location: `src/Domain/Checker/`

Implement `AiCheckInterface`.

Example outline:

- `id()` returns a stable string like `length_guard`
- `supports($fieldName, $fieldType)` returns true only for the fields/types it should run on
- `check($fieldName, $values)` returns a `CheckReport` with Findings

### 2) Register it as a tagged service
In `ai_output_checks.services.yml`:

```yaml
  Drupal\ai_output_checks\Domain\Checker\LengthGuardCheck:
    arguments: []
    tags: [ { name: ai_output_checks.check } ]
```

---

# TODO
## Node saving
  - `CheckContentRegexp` performs node saves in `getOrCreate` and `syncParagraphRevisionOnNode`. This should probably be done in ECA