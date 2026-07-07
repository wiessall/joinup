#+TITLE: ATIO KB / STI Portal — Project State, Gaps & Backlog
#+SUBTITLE: Consolidated from ~3 weeks of exploration (2026-05-08 → 2026-05-25)
#+AUTHOR: (your STI/ATIO track)
#+STARTUP: overview
#+TODO: TODO NEXT SOMEDAY | DONE DECIDED

* 1. The spine (why, in one paragraph)
Drupal content-types are the wrong primitive for a contested, multi-source domain.
"Innovation" is not a natural kind; it is a /classification applied by a collection
owner/ to an underlying real-world initiative. The right model is therefore: a thin,
identity-bearing core (the anchor), a per-collection set of attributed *claims*
(each source's faithful viewpoint), and a governed *reconciliation* (the ATIO claim)
that is "just another viewpoint" but the canonical one. Provenance (source → extracted →
published) is a *lineage* axis (PROV-O), NOT a FRBR abstraction axis — don't conflate them.
The team design doc's own commitments (SVoT-not-SSoT, data mesh, FAIR, LOD/SKOS,
field-level AI stamps) already describe this model; the current *implementation* is the deviation.

* 2. Where you are (established / working)
** Conceptual decisions (DECIDED)
- DECIDED Adopt Joinup pattern (twin DB: relational for pipeline/workflow, triplestore for core assets + claims).
- DECIDED Innovation = thin core (~10) + claims, not an 80-field monolith.
- DECIDED Annotations renamed to *claims* = a collection's viewpoint on the data.
- DECIDED AI enrichment is additive/optional, run *after* honest sparse import — never gap-fill at import.
- DECIDED Core innovation maps to dcat:Dataset / adms:Asset; 5–10 DCAT-AP predicates (dct:title, dct:description, dct:spatial, dct:created, dct:creator, schema:url, dct:type…).
- DECIDED Taxonomies = SKOS; reuse AGROVOC / UN M49 / CGIAR scales; mirror AGROVOC locally with AGROVOC URIs as canonical.
- DECIDED Provenance ≠ FRBR. source→extracted→published is PROV-O lineage. FRBR/identity belongs to co-reference.
** Working artifacts (DONE)
- DONE Joinup ddev stack running (Drupal, Virtuoso, Solr, memcached); rdf_sync writing triples.
- DONE Standard JSON intermediate schema (meta + records + _raw + _annotations); Python normalizer (csv/xlsx → JSON).
- DONE Generic migrate_plus templates (1 per content type, ~3 lines change per collection — not 1 per collection).
- DONE Test collections imported (e.g. bp5, Digital Green, ECA Agritech) + per-collection annotation sets (Drupal + RDF).
- DONE Innovation profile: per-record table of _annotations key/values.
- DONE TriG model formalized (schema / content / per-collection statement graphs / catalog provenance).
- DONE SKOS curation HTML placeholder (demo-grade); show-and-tell SPARQL queries.
- DONE sti_rdf module: URI generator, annotate Drush command, OG-group (og_audience) collection wiring.
** Infra / decisions still loose
- Module boundaries (proposed 3-way split: import_IRD_jsons / sti_annotation / sti_rdf) — not finalized.
- Collection filtered-search view auto-generated from annotation fields — partially attempted, hit OG-recognition dead-ends.
- VocBench / GraphDB / Loddy — planned, not stood up. Neo4j — only "under evaluation".

* 3. What you're missing (gaps)                                          :gaps:
** Strategic / governance (raised early, never closed) — HIGH VALUE
- TODO [#A] Name a *success metric* and an *accountable owner* for the KB track (means vs. end).
- TODO [#A] Decide *build-vs-link per source*; treat IP/licensing as the same decision (Nature = legal exposure).
- TODO [#B] Re-confirm scope boundary: KB vs. STI Social vs. Challenges vs. AI-tooling (4 different products).
** The load-bearing technical gap
- TODO [#A] *Co-reference / deduplication* — no method, no ADR. Everything (merge, family, org-gen, SVoT) depends on it.
** Implementation gaps vs. the design doc's OWN bar (low controversy)
- TODO [#A] No AI provenance/tracing: no prompt versioning, no output versioning (doc specifies field_ai_stamp).
- TODO [#A] AI enriches below evidence threshold → slop/hallucination (no sufficiency gate).
- TODO [#A] No golden evaluation dataset; QA is a fragile PHP script; no curation GUI; no LLM-assist.
- TODO [#A] Pivotal annotate script (Drupal+RDF) is untested AI-written code.
- TODO [#B] No "metadata concept" (record-level provenance/licensing/QA separated from descriptive content).
- TODO [#B] No agreed *minimal required fields* per type (needs SHACL or a validation gate).
** Plumbing / correctness (from the last fix list — verify done)
- TODO [#B] Load the *schema graph* into Virtuoso (class declarations exist in TriG but not in store).
- TODO [#B] Normalizer hygiene: split pipe-delimited values; drop pandas NaN; drop empty-string literals (absent ≠ blank).
- TODO [#B] Move org attributes (type/size/website) off the annotation onto the Organization node.
- TODO [#B] Fix sti:statedBy targets; declare sti:annotates / sti:statedBy as properties; add an IRD-system node.
- TODO [#C] rdf_sync robustness: referenced bundles (collection, owner, taxonomy_term) must all have rdf_sync config or serialization throws null-URI.
** Operational
- TODO [#B] Enrichment runs synchronously inside import and clogs the API (~50/run) — needs decoupling + parallelism.
- TODO [#C] No idempotent/safe re-import + rollback story beyond ird:rollback-annotation.
- TODO [#C] A stable "demo kit" (rendered collection page + SPARQL show-and-tell + SKOS UI) keeps getting rebuilt ad hoc.

* 4. Backlog (prioritized: decide → hygiene → evidenced → bets → later)
** Tier 0 — Cheap decisions that unblock code                            :decide:
- NEXT [#A] ADR-0: Where does reconciliation happen? (extracted layer vs. ATIO-claim layer).
  Your ADR #6 already leans to the ATIO-claim layer (extracted innovations stay out of the triplestore). Make it explicit — it keeps the 3 axes clean.
- NEXT [#A] ADR: Build-vs-link + IP/licensing per source (one decision). Output: a per-source reuse-rights field that travels with every claim.
- NEXT [#A] Write the KB success metric + owner into the project plan.
** Tier 1 — Urgent hygiene ("meets our own design-doc bar")             :hygiene:
- NEXT [#A] AI provenance: stamp every AI field with provider/model/prompt-version/datetime; version prompts in git; version outputs.
- NEXT [#A] AI sufficiency gate: below an evidence threshold, write NOTHING (not a plausible guess).
- NEXT [#A] Decouple enrichment from the import path → async parallel job; remove the ~50 cap.
- NEXT [#A] Stand up a *golden evaluation dataset* (few hundred records, known-good enrichment) — prerequisite for any judge-AI/QA claim.
- TODO [#A] Test harness around the annotate script (the untested Drupal+RDF writer).
- TODO [#B] Curation review view (flagged content, per-field QA, bulk ops).
- TODO [#B] Load schema graph + run the normalizer-hygiene fixes (Section 3 plumbing).
** Tier 2 — Evidenced low-risk wins (already piloted)                   :evidenced:
- TODO [#A] ADR #1: Standard JSON import schema + Python parser. Formalize: JSON-Schema validation + version-control the import contract (fixes "the real SoT is a fragile external CSV").
- TODO [#A] ADR #4: Load data fully as-is; immutable, versioned Original Record (cheap storage; enables the Rosetta goal).
- TODO [#A] ADR #2: Reduce/relocate core fields (80 → ~10 core + ATIO claim + collection claims). Frame as *relocation, not deletion*; prove the composed profile renders as richly as the monolith.
- TODO [#B] Metadata concept + minimal-required gate (SHACL later, simple validation now; quarantine below the bar). Fold IP/licensing in here.
** Tier 3 — Strategic bets (pilot, then propose)                            :bets:
- TODO [#A] ADR #6: Simplified data model (thin innovation + claims + provenance). Mostly prototyped in TriG; productionize.
- TODO [#B] ADR #5: Dual SQL/Virtuoso (Joinup pattern; already on the doc's roadmap). Biggest infra cost → contained pilot.
- TODO [#B] ADR #3: AI rearchitecture — observable, versioned, parallel, judge-AI pipeline OFF the request path.
  Keep ECA as trigger/workflow; move heavy orchestration to Python (extends the doc's "Python at the boundaries" principle). LangChain = pilot detail, not the decision.
- TODO [#A] ADR (NEW): Co-reference / dedup as an explicit, human-confirmable governed decision (not an emergent similarity threshold). Even if the decision is "defer with interim."
- TODO [#B] ADR #7: SKOS mapping interface (VocBench, or promote the HTML placeholder). Depends on #5/#6.
** Tier 4 — Later / someday                                              :later:
- SOMEDAY Field-level temporal tracking — how claims version over time (doc OPEN ISSUE 3).
- SOMEDAY Innovation families: KOS vs entities (doc OPEN ISSUE 1/1b) — claims model should be able to host either.
- SOMEDAY Knowledge-graph visualization (D3 / Cytoscape over SPARQL).
- SOMEDAY Multilingual strategy.
- SOMEDAY "Rosetta stone" cross-vocabulary mapping (enabled by load-as-is + SKOS + claims).

* 5. Resources / reading (collected across the project)
** Standards & vocabularies
- ADMS-AP for Joinup (Collections=dcat:Catalog, Solutions/Releases=dcat:Dataset, Distributions=dcat:Distribution); Joinup technical architecture page.
- DCAT / DCAT-AP; dcat:CatalogRecord (separates dataset from the record describing it).
- SKOS (W3C); skos:exactMatch / closeMatch / broadMatch for taxonomy alignment.
- PROV-O (W3C, Lebo et al. 2013) — Entity/Activity/Agent; the correct lens for source→extracted→published.
- AGROVOC — agrovoc.fao.org (SKOS-RDF + SPARQL); UN M49; CGIAR Scaling Readiness (scalingreadiness.org); IDIA scaling stages.
** Theory (identity / classification)
- FRBR / IFLA-LRM (WEMI; nomen/appellation — the "same innovation, many names" tool).
- OntoClean (Guarino & Welty) — rigidity/identity/unity; "innovation" is anti-rigid (a role/classification).
- Data Mesh (Dehghani, 2022) — domain-owned products federated into a global product = your SVoT framing.
- Ranganathan faceted classification / PMEST; product-family theory (Meyer & Lehnerd) — for innovation families.
** Tools
- VocBench 3 (FAO uses it for AGROVOC) — SKOS management. GraphDB/RDF4J backend; Loddy for URI resolution.
- WebVOWL (service.tib.eu/webvowl) — drop a TTL, get an ontology diagram; Protégé for editing.
- Virtuoso faceted browser (/fct/) + /sparql; YASGUI (yasgui.triply.cc) pointed at the endpoint.
- Wikidata Query Service (query.wikidata.org) — to see SPARQL on real data.

* 6. Still-open questions (carry into the next team session)
- DECIDED-ish "What is an innovation?" → thin core + claims; "innovation" is a classification, not the anchor.
- TODO Minimal core field set — which ~10, which are *required* (gate) vs. optional?
- TODO Build vs. link (per source) — and the IP decision that rides with it.
- TODO Who owns the KB direction vs. the design-doc team? What's the success metric?
- TODO Where does reconciliation live, and how is co-reference decided and recorded?