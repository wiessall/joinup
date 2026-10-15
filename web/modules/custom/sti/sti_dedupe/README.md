# STI Duplicate Resolution (`sti_dedupe`)

Post-import review of possible duplicate solutions, and curator rulings on
claims that cannot all be true.

## Model

- A **solution** is a source record: one collection's account of an
  innovation. It is never merged or deleted by this module.
- "These two solutions are the same innovation" **lives on the solutions**:
  each one gets a `sameInnovationAs` claim naming the other, attributed to
  its own collection like every other claim on it, and mirrored into
  `related_solutions`. In the graph:
  `?s sti:hasFieldClaim ?c . ?c sti:claimProperty <sameInnovationAs term> ; sti:claimRefValue ?other`.
- An **ATIO innovation** (`atio_innovation`) is the curated thing. It lists
  every solution it was derived from in `atio_derived_from`
  (`atio:derivedFromSource`), each pinned at a revision.
- When an ATIO innovation exists, the link is also reflected there: the
  innovation lists every solution of the cluster in `atio_derived_from`.
- **Claims** stay on the solutions, attributed to their collections. Where
  the sources of one innovation disagree on a *single-valued* ATIO property,
  a curator rules which value is authoritative.

## Duplicate review

```
drush sti-dedupe:scan <collection>    → candidates, precomputed into a table
/admin/content/dedupe                 → collections with pending reviews
/admin/content/dedupe/{collection}    → queue, best score first
  → resolve/{solution}/{candidate}    → side by side, two outcomes
```

| Button | What happens |
|---|---|
| Same innovation | The decision is recorded, and every solution in the *cluster* (the pair plus everything already decided "same" with either) gets a `sameInnovationAs` claim naming every other member. The cluster also goes onto the innovation(s) derived from any of them. No innovation yet → decision and claims only. One → every cluster member is added. Several → the reviewer picks a survivor; it gains every source, the others are unpublished, recorded as replaced, and their URIs redirect to the survivor. |
| Not the same | The decision is recorded and the pair is never offered again. Refused if the pair was already decided "same": undoing a link is a curator's job. |

Scoring uses core fields only (title, owner, country, year), never mapped
claim values: duplicate review must not wait on vocabulary mappings.

## TODO Claim conflicts

```
/admin/content/dedupe/conflicts             → pending and stale resolutions
/admin/content/dedupe/conflicts/{paragraph} → rule: one value authoritative
drush sti-dedupe:conflicts [--innovation=N] → compute now instead of on cron
```

A claim is compared only when its collection's property term is mapped to an
ATIO property (`field_atio_property` on `claim_properties`). Unmapped
properties are never compared. Nothing waits on a mapping, and nothing is
guessed from property names.

A property is **single-valued** when the `atio_innovation` field that emits
it (via its rdf_sync mapping) is a configurable, non-narrative field with
cardinality 1: readiness level, origin country, start year, the grassroots
flag. Titles and descriptions are never compared. The settings form shows the
computed list and takes additions and removals.

Values compare by `field_skos_exact_match` on the `claim_values` term when it
has one, otherwise by normalised label.

Each conflict is a `claim_resolution` paragraph on the innovation
(`atio_claim_resolutions`), with one `claim_verdict` per claimed value:

- New conflict → *pending*, every value *unreviewed*.
- Untouched pending → follows the claims; removed when the sources agree.
- Ruled → *resolved*. When the claims change later the ruling is **kept**:
  verdicts on values no longer claimed are flagged not current, new values
  are added unreviewed, and the resolution becomes *stale*.

Verdicts identify a claim by collection + property + value, never by claim
paragraph id, because `ird_claims` deletes and recreates a collection's
claim paragraphs on every re-import. An identical re-import changes nothing.

A ruling is recorded only. Copying the authoritative value into the
innovation's own field is left to the curator or the enrichment pipeline.

Refreshes run on cron (queue `sti_dedupe_conflict_refresh`), triggered by a
solution save, a change to an innovation's sources, or a mapping edit (one
full pass, however many terms are edited).

## Things that fail silently, and what guards them

| Failure | Guard |
|---|---|
| A link half-written (some sources added, loser still published, replacement not recorded) | One transaction; `Linker::verify()` reads storage back and rolls back with an error log. |
| A solution left without its sameInnovationAs claim | Every subject is checked before any is written; `SameAsClaims::verify()` reads storage back inside the link transaction; `LinkerTest`. |
| A re-import deletes the sameInnovationAs claims (they are attributed to the importing collection) | `ird_claims` keeps claims whose property term sits at the root of `claim_properties` (`IrdClaims::isSystemClaim()`); `SameAsClaimsTest::testReimportKeepsSameAsClaims()`. |
| A refused link leaves claims in a memcache entity cache after the SQL rollback | The survivor check runs before any write; entity caches of the cluster are reset on rollback. |
| A refresh leaves resolutions out of step with claims | `ResolutionReconciler::verify()`, same pattern. |
| A ruling saved against values the curator never saw | `StaleResolutionException` when the claimed values changed since the form loaded. |
| A re-import silently discards a ruling | Tuple identity, `ClaimResolutionTest::testRulingSurvivesIdenticalReimport()`. |
| Hooks stop queueing refreshes | `ConflictQueueTest`. |
| A replaced innovation's URI starts to 404 | `ReplacedInnovationSubscriber` on `sti_rdf`'s `UriResolveEvent`; `LinkerTest`. |

Rollback covers SQL only. rdf_sync writes to the triplestore as entities are
saved, so after a failed link, re-sync the innovations involved.

## Tests

```
ddev exec 'vendor/bin/phpunit -c phpunit.xml web/modules/custom/sti_dedupe'
```

Kernel tests boot core and contrib only (node, taxonomy, paragraphs,
entity_reference_revisions, rdf_sync, sti_rdf), never the Joinup
distribution. Fields owned by other modules are created with their site
names. sti_dedupe's own fields are installed from its config/install, minus
rdf_sync mappings, so the shipped config is what gets tested.
