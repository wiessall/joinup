# STI Duplicate Resolution (`sti_dedupe`)

Post-import review of possible duplicate solutions. Implements ADR #12: the
migration never merges, a reviewer decides afterwards, and every outcome is
additive or reversible.

## Why it works this way

Loading a second collection covering the same innovations raises two questions
— what to do when core fields disagree, and whether to merge records.
Answering them automatically needs a resolution policy, per-property
tie-breaks and trust weights, all of which mutate data at import time and are
hard to undo. A human answers them instead, so none of that machinery exists.

Two constraints shaped the code:

- **Core fields only.** Scoring and the comparison table use title, owner,
  country and year. They must never consume mapped claim values, because that
  would make review depend on vocabulary-mapping coverage, and ADR #14
  requires that nothing waits on a mapping.
- **Claims are additive.** Two collections claiming different things about one
  AFSI is the normal case, not a conflict, so claims are summarised rather
  than diffed.

## Flow

```
drush sti-dedupe:scan <collection>     → precomputes candidates into a table
/admin/content/dedupe                  → collections with pending reviews
/admin/content/dedupe/{collection}     → queue, best score first
  → resolve/{solution}/{candidate}     → side-by-side, three outcomes
```

Scanning is a batch step, not per-request work: at 200 collections of ~1400
solutions, a search per row per page load would not be acceptable.

## The three outcomes

| Button | What happens |
|---|---|
| Same — keep existing | claims and `field_source_records` re-parented onto the survivor; survivor shared into the loser's collection; migrate map repointed; redirect created; decision recorded; loser deleted |
| Same — keep both | a `sti:sameInnovationAs` claim written on each side, attributed to that side's collection |
| Not the same | decision recorded so the pair is never offered again |

### Four things that fail silently if you change them

1. **The migrate map must be repointed.** After the loser is deleted,
   `migrate_map_<id>` still maps its source row to a dead nid, so the next
   import recreates the duplicate and undoes the decision. `Merger` rewrites
   `destid1` through the id map's own table — there is no entity-level API for
   this. With it done, `ird_claims`'s per-collection delete-and-recreate
   correctly refreshes the merged record instead of forking it again.
2. **Paragraphs are re-parented before the delete.**
   `entity_reference_revisions` is a composite relationship, so deleting the
   loser would take its claims with it. They are re-parented and attached to
   the survivor, then removed from the loser, then the loser is deleted.
   Re-parenting rather than cloning keeps paragraph ids, and therefore the
   FieldClaim URIs minted from them, stable across a merge.
3. **"Not the same" is recorded.** Without it reviewers redo the same pair on
   every re-import.
4. **Sharing, not `og_audience`.** `og_audience` has cardinality 1, so the
   survivor keeps its own collection and gains a Joinup `Sharing` for the
   other — visibility without edit rights.

### Decisions survive node deletion on purpose

`hook_node_delete()` clears candidate rows but **not** decisions. A merge
records its decision and then deletes the losing node; clearing decisions on
delete would erase the record of the merge that had just been made. A decision
naming a deleted node is harmless and is the audit trail.

## Predicate choice

`sti:sameInnovationAs`, declared non-transitive — deliberately not
`owl:sameAs`, which asserts identity of individuals (so a reasoner merges the
two titles the reviewer explicitly refused to merge) and is transitive (so one
bad link contaminates a chain). `prov:alternateOf` is the closest standard
alternative if you would rather not mint a term.

### Why not `related_solutions`?

Joinup's solution bundle already has `related_solutions` — an unlimited
node→node reference described as "Related solutions (similar, same context,
same application, etc.)", rendered by the stock tile, teaser, carousel and
group-header displays.

It is not a substitute for the claim, because it carries no attribution (who
said so, when), no relation type, and has no `rdf_sync` mapping, so nothing
reaches the graph. But it is the right place to *show* the link. So "Same —
keep both" writes both: the claim is the record, `related_solutions` is a
projection of it — the same write-model/read-model split the core fields use.

Joinup does use `owl:sameAs` once, on `field.field.node.licence.spdx_licence`,
where a licence node really is the identical thing as its SPDX entry. That is
the correct use of it, and it is why it is the wrong predicate for two
distinct curated records that a reviewer has just chosen to keep apart.

## Config it installs

`field_claim_ref_node` on the `claims` paragraph — an entity reference to
`node.solution`. The existing `field_claim_ref_value` only targets taxonomy
terms, so a claim whose object is another solution had nowhere to point. If
the field is missing, the code falls back to writing the node's absolute URI
into `field_claim_literal_value`.

## Settings

`/admin/config/sti/dedupe` — threshold (default 0.8), max candidates per
solution, which Search API indexes to search (newly imported solutions are
usually unpublished, so `unpublished` is included by default), which migration
tags own the id maps to repoint, and which core fields the comparison shows.

## Not done yet

- No test coverage. `Merger::keepExisting()` is the part that most wants a
  kernel test: paragraph re-parenting, map repointing and delete ordering are
  exactly the things that break quietly.
- `field_source_records` (ADR #11) does not exist yet. The merger already
  moves it when present, so nothing changes here when it lands.
- Scoring is deterministic string similarity. `ai_output_checks` already
  solves fuzzy organisation matching; reusing its scorer would likely beat
  `similar_text()` on titles.
- The queue lists one row per solution with its best candidate. Walking the
  other candidates happens on the resolution screen.
