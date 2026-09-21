# Joinup PoP: phased backlog

**Goal:** Prove that our STI portal data works as a knowledge graph using the Joinup/Interoperable Europe Portal distribution.

**Success criteria:** On Friday, show a working page with real STI innovations stored as RDF entities in Virtuoso, queryable via SPARQL, browseable in Drupal with faceted search.

**Starting point:** Joinup ddev environment running locally (Docker, MySQL, Virtuoso, Solr, memcached all up).

---

## Phase 0: Clean install

Get the empty Joinup site running with all services healthy.

| # | Task | Where | Effort | Notes |
|---|------|-------|--------|-------|
| 0.1 | Fix NixOS shebang issue on ddev commands | Host | S | `sed -i '1s\|#!/bin/bash\|#!/usr/bin/env bash\|' .ddev/commands/host/*` |
| 0.2 | Run `ddev install` (clean install, no EU data) | ddev | S | This installs the Joinup profile with empty content. |
| 0.3 | Verify site loads at `http://127.0.0.1:<port>` | Browser | S | Login with `ddev drush uli`. |
| 0.4 | Verify Virtuoso SPARQL endpoint responds | Browser | S | Access Virtuoso via `ddev ssh -s virtuoso` or find the mapped port in `ddev describe`. Try: `SELECT * WHERE { ?s ?p ?o } LIMIT 10` |
| 0.5 | Verify Solr is indexing | Browser | S | Check Solr admin at mapped port. |

**Done when:** Empty Joinup site loads, you can log in as admin, Virtuoso and Solr are both responding.

---

## Phase 1: Understand the content model

Before loading data, understand what Joinup gives you out of the box.

| # | Task | Where | Effort | Notes |
|---|------|-------|--------|-------|
| 1.1 | Create a Collection manually via the UI | Drupal admin | S | A Collection is analogous to an "initiative" or "programme" in STI terms (e.g., "Climate-Smart Agriculture Innovations"). |
| 1.2 | Create a Solution inside that Collection | Drupal admin | S | A Solution is analogous to an "Innovation." This is the core RDF entity. Note what fields are available — they follow ADMS-AP. |
| 1.3 | Verify the Solution appears in Virtuoso | SPARQL endpoint | S | Query: `SELECT * WHERE { ?s a <http://www.w3.org/ns/dcat#Dataset> } LIMIT 10`. The Solution should appear as a `dcat:Dataset`. |
| 1.4 | Verify the Solution appears in Solr | Solr admin | S | Check the search index — the CQRS pattern means the entity should be in both Virtuoso and Solr. |
| 1.5 | Inspect the RDF entity type configuration | Drupal admin / code | M | Look at `rdf_entity` bundles. Understand how fields map to RDF predicates. Key files: anything under `web/modules/custom/joinup_*` that defines entity types. |
| 1.6 | Read `docs/sparql.md` in the repo | Local | S | Joinup's own SPARQL developer docs. |
| 1.7 | Map Joinup concepts to STI concepts | Paper / notes | M | Collection → Initiative/Programme. Solution → Innovation. Release → Version (maybe skip). Distribution → Resource/Download (maybe skip). Organisation → Organisation. Person → Person. |

**Done when:** You've created a test Collection + Solution, confirmed they're in the triple store, and have a written mapping from Joinup vocabulary to STI vocabulary.

---

## Phase 2: Load STI data

Get real innovations from the STI portal into Joinup as RDF entities.

| # | Task | Where | Effort | Notes |
|---|------|-------|--------|-------|
| 2.1 | Export innovations from STI portal via JSON:API | Host (Python) | M | `https://sti-portal.fao.org/jsonapi/node/innovation` — paginate through all results, save to local JSON. Check if the API is accessible from your machine. If not, use a Drush export or direct DB dump instead. |
| 2.2 | Export organisations from STI portal | Host (Python) | S | Same approach. |
| 2.3 | Map STI fields to ADMS-AP predicates | Paper / notes | M | For each STI field, decide: does it map to an existing ADMS-AP predicate (e.g., `dct:title`, `dct:description`, `dct:spatial`), or should it be skipped for the PoP? Keep it to 8–10 fields max. |
| 2.4 | Write the migration script | Host (Python + rdflib) | L | Read JSON, produce triples in ADMS-AP shape, write to Virtuoso via SPARQL UPDATE or the Graph Store Protocol. Target: innovations as `dcat:Dataset` inside a Collection, organisations as `org:Organization`. |
| 2.5 | Load triples into Virtuoso | ddev / SPARQL endpoint | M | Run the script. Verify with a count query: `SELECT (COUNT(*) AS ?c) WHERE { ?s a <http://www.w3.org/ns/dcat#Dataset> }`. |
| 2.6 | Trigger Solr reindex | ddev | S | `ddev drush search-api:rebuild-tracker` then `ddev drush search-api:index`. |
| 2.7 | Verify innovations appear in Drupal | Browser | S | Browse the Collection page — your innovations should appear in the listing with faceted search. |
| 2.8 | Verify relationships work | Browser | S | Click an innovation → see its linked organisation. Click the organisation → see its innovations. This is the "graph" part working through Drupal's UI. |

**Done when:** Real STI innovations are browseable in Joinup's interface with working relationships and faceted search, stored as RDF in Virtuoso.

**Alternative fast path (if the migration script takes too long):**

| # | Task | Effort | Notes |
|---|------|--------|-------|
| 2.alt | Create 5–10 innovations manually through the Drupal UI | M | Use real data from the portal, entered by hand. Less impressive but still proves the architecture. |

---

## Phase 3: SPARQL queries that Drupal can't answer

Write the queries that justify the knowledge graph. These are your demo's punctuation marks.

| # | Query | What it proves | Effort |
|---|-------|----------------|--------|
| 3.1 | All innovations by country | Basic: data is in the graph | S |
| 3.2 | All organisations connected to innovations in a specific domain | Cross-entity traversal — two hops, one query | S |
| 3.3 | Count of innovations by country and topic | Aggregation across the graph | S |
| 3.4 | Countries with innovations on topic X but no partner organisation | Gap analysis — `NOT EXISTS` pattern. Impossible in Drupal Views. | M |
| 3.5 | Innovations linked to AGROVOC narrower concepts of "irrigation" | Linked data payoff — traverses the AGROVOC hierarchy. Only works because both datasets are RDF. | M (requires AGROVOC loaded) |

**Done when:** You have 3–5 queries saved, each with a one-sentence explanation of why it matters and why it can't be done in the current portal.

---

## Phase 4: AGROVOC integration (stretch)

Prove the linked-data integration with FAO's own vocabulary.

| # | Task | Where | Effort | Notes |
|---|------|-------|--------|-------|
| 4.1 | Download AGROVOC RDF dump | Host | S | `https://agrovoc.fao.org/` — available as N-Triples. ~200MB uncompressed. |
| 4.2 | Load into Virtuoso | ddev / Virtuoso | M | May need Virtuoso config tweaks for large file import. Load into a separate named graph. |
| 4.3 | Run query 3.5 (AGROVOC narrower concepts) | SPARQL endpoint | S | This is the "wow" moment — traversing FAO's own thesaurus within the same query as your innovations. |

**Done when:** A SPARQL query can combine STI innovation data with AGROVOC's concept hierarchy in a single result set.

---

## Phase 5: AI layer comparison (stretch)

Connect the knowledge graph to the existing Milvus pipeline and show the improvement.

| # | Task | Where | Effort | Notes |
|---|------|-------|--------|-------|
| 5.1 | Generate graph-enriched text per innovation | Host (Python) | M | Query Virtuoso for each innovation + relationships + AGROVOC terms. Serialise as a paragraph. |
| 5.2 | Embed and load into Milvus | Host (Python) | M | Use the same embedding model the team already uses (`text-embedding-3-small`). |
| 5.3 | Side-by-side comparison | Demo | S | Same question, two answers: one from current Milvus (flat Drupal scrape), one from graph-enriched Milvus. The graph version should be visibly richer. |

**Done when:** You can show that the knowledge graph produces better AI retrieval than the flat Drupal export.

---

## Phase 6: Demo (Friday)

| # | Beat | What you show | Duration |
|---|------|---------------|----------|
| 6.1 | "This is Joinup running locally with our data" | Browse the Collection, click through innovations, show faceted search | 2 min |
| 6.2 | "The data is stored as a knowledge graph" | Open the SPARQL endpoint, run query 3.1 (basic) and 3.4 (gap analysis) | 3 min |
| 6.3 | "It connects to FAO's own vocabulary" | Run query 3.5 if AGROVOC is loaded. If not, skip. | 1 min |
| 6.4 | "The AI gets better data" | Side-by-side Milvus comparison if done. If not, show the graph-enriched text and explain. | 2 min |
| 6.5 | "This is the EU's platform, running for 10 years" | Name Joinup, the EUPL licence, the twin-database pattern. "We didn't build this; we're reusing it." | 1 min |
| 6.6 | "Here's the path forward" | Reference the architecture diagrams, the ADRs, the product landscape. This isn't a prototype — it's the beginning of a real architecture. | 2 min |

---

## Risks and fallbacks

| Risk | Likelihood | Fallback |
|------|-----------|----------|
| `ddev install` fails on clean install | Medium | Debug logs, check Drupal install profile errors. Worst case: manually install Drupal and enable Joinup modules one by one. |
| STI portal JSON:API not accessible externally | Medium | Export via Drush on the portal server, or use a DB dump. |
| Migration script mapping is harder than expected | Medium | Fall back to manual entry of 5–10 innovations (Phase 2 alt path). |
| AGROVOC too large for local Virtuoso | Low | Skip Phase 4, keep it as a "next step" in the demo. |
| Milvus integration too complex for this week | High | Skip Phase 5, explain verbally with the architecture diagram. |

---

## Blog post material

Document everything. The NixOS + Docker + ddev setup alone is a blog post:

- NixOS flake for Joinup dev environment
- Podman vs Docker — why ddev needs Docker Engine
- ddev shebang fix for NixOS (`#!/bin/bash` → `#!/usr/bin/env bash`)
- Immutable `/etc/hosts` workaround
- Loading STI portal data as RDF into Virtuoso
- SPARQL queries that Drupal Views can't answer
