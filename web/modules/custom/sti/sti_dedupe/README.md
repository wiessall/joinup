# STI Duplicate Resolution (`sti_dedupe`)

Post-import review of possible duplicate solutions, decided per collection,
and reconciliation of ATIO innovations by curators.

## Model

- A **solution** is a source record: one collection's account of an
  innovation. It is never merged or deleted by this module.
- "This record is the same innovation as that one" is **a collection's
  claim about its own record**: one `sameInnovationAs` claim on the
  collection's solution, attributed to that collection and mirrored into its
  `related_solutions`. The other record is never written. Claims are
  therefore one-way; readers follow both directions. In the graph:
  `?s sti:hasFieldClaim ?c . ?c sti:claimProperty <sameInnovationAs term> ; sti:claimRefValue ?other ; sti:claimedBy ?collection`.
- Decisions are **per collection**: collection B can decide the same pair for
  its own record, and A's "not the same" does not hide the pair from B.
- An **ATIO innovation** (`atio_innovation`) is the curated thing. Its
  `atio_derived_from` (`atio:derivedFromSource`) says what it was actually
  derived from, each source pinned at a revision. **A decision does not
  change it.** Claimed duplicates are an input for the innovation's next
  update: the "Possibly the same" pseudo-field (place it in any
  `atio_innovation` display) lists them via `SameAsClaims::linkedTo()`, one
  hop. A record joins `atio_derived_from` when an update actually uses it.
- Only **ATIO curators** change innovations: when records decided "same" sit
  on different innovations, a curator may merge them.

## Duplicate review

```
drush sti-dedupe:scan <collection>          → candidates, precomputed into a table
/node/{collection}/duplicates               → the collection's queue ("Duplicates" tab)
  → /{solution}/{candidate}                 → side by side, two outcomes
/admin/content/dedupe                       → curators: every queue, innovations to reconcile
  → /reconcile/{a}/{b}                      → pick a survivor, or leave them apart
```

Access to a collection's queue: the OG permission **review collection
duplicates** (grant it to the collection facilitator role), or the site
permission **review sti duplicates** (ATIO curators). Reconciling needs the
site permission.

| Button | What happens |
|---|---|
| Same innovation | The collection's decision is recorded and its own record gets one `sameInnovationAs` claim naming the other. Nothing else is written. |
| Not the same | The collection's decision is recorded and the pair is not offered to it again. Refused if the collection already decided "same". |
| Merge innovations (curators) | The survivor takes over the others' `atio_derived_from` entries, nothing else; the others are unpublished, recorded as replaced, and their URIs redirect to the survivor. |

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
| A claim written on another collection's record | Route access per collection; `Linker` refuses a subject outside the collection; `LinkerTest::testSubjectOutsideCollectionIsRefused()`. |
| A decision that changes an innovation | `LinkerTest::testSameLeavesInnovationsUntouched()`. |
| A decision recorded without its claim | One transaction; `SameAsClaims::claim()` reads storage back and throws; `LinkerTest::testFailedClaimRollsBackTheDecision()`. |
| The lookup misses one direction, so curators see too few duplicates | `SameAsClaimsTest::testLinkedToFollowsBothDirectionsOneHop()`. |
| A merge half-written (sources not taken over, loser still published, replacement not recorded) | One transaction; `Linker::verifyMerge()` reads storage back and rolls back with an error log. |
| Update 11003 assigns old decisions to the wrong collection | `DecisionStoreTest::testUpdateAssignsOldDecisionsToBothCollections()`; the hook throws if rows are left unassigned. |
| A re-import deletes the sameInnovationAs claims (they are attributed to the importing collection) | `ird_claims` keeps claims whose property term sits at the root of `claim_properties` (`IrdClaims::isSystemClaim()`); `SameAsClaimsTest::testReimportKeepsSameAsClaims()`. |
| A refused write leaves entities in a memcache entity cache after the SQL rollback | Entity caches of the nodes involved are reset on rollback. |
| A refresh leaves resolutions out of step with claims | `ResolutionReconciler::verify()`, same pattern. |
| A ruling saved against values the curator never saw | `StaleResolutionException` when the claimed values changed since the form loaded. |
| A re-import silently discards a ruling | Tuple identity, `ClaimResolutionTest::testRulingSurvivesIdenticalReimport()`. |
| Hooks stop queueing refreshes | `ConflictQueueTest`. |
| A replaced innovation's URI starts to 404 | `ReplacedInnovationSubscriber` on `sti_rdf`'s `UriResolveEvent`; `LinkerTest`. |

Rollback covers SQL only. rdf_sync writes to the triplestore as entities are
saved, so after a failed write, re-sync the entities involved.

## Tests

```
ddev exec 'vendor/bin/phpunit -c web/core web/modules/custom/sti/sti_dedupe'
```

Kernel tests boot core and contrib only (node, taxonomy, paragraphs,
entity_reference_revisions, og, rdf_sync, sti_rdf), never the Joinup
distribution. OG is enabled only for the group permission; `og_audience` is
a plain reference. Fields owned by other modules are created with their site
names. sti_dedupe's own fields are installed from its config/install, minus
rdf_sync mappings, so the shipped config is what gets tested.
