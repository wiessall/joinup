# Knowledge graphs for the STI platform
## A briefing for the team

_Last updated: 2026-04-17_

This document answers four questions that keep coming up in discussions about the STI portal's future architecture. It's written to be shared — no jargon without explanation, no hand-waving.

---

## 1. Why a knowledge graph, and how does it actually work?

### The problem it solves

The STI portal stores innovations as Drupal nodes. Each innovation is a record with ~40 fields. Different teams want different fields. Every new request means a developer adds a field, the edit form gets longer, and nobody uses most of them. We've reached the point where the content type is unsustainable.

Worse: the interesting questions aren't about single records. They're about *connections*:

- "Which innovations in soil degradation have been tested in semi-arid regions by organisations we're partnered with?"
- "Show me all people connected to climate-smart agriculture innovations in East Africa, across any organisation."
- "Which innovations scored high on gender impact *and* climate resilience, but low on investment readiness?"

In Drupal, each of these requires a custom View that someone builds manually. If the question changes slightly, you need a new View. If it spans content types (innovations + organisations + people), you need entity-reference fields configured in advance for every possible connection. The system can only answer questions that a developer anticipated.

### What a knowledge graph is

A knowledge graph stores information as **triples**: subject → predicate → object.

```
Innovation:drip-irrigation-kenya  →  locatedIn       →  Country:kenya
Innovation:drip-irrigation-kenya  →  addressesTopic  →  Topic:soil-degradation
Innovation:drip-irrigation-kenya  →  developedBy     →  Org:icrisat
Org:icrisat                       →  partneredWith   →  Org:fao
Person:jane-doe                   →  worksAt         →  Org:icrisat
Person:jane-doe                   →  evaluatedItem   →  Innovation:drip-irrigation-kenya
```

That's it. There's no "innovation content type" with 40 fields. There's an innovation *entity* with as many or as few statements as people have made about it. A new team that wants to track "gender impact" doesn't need a developer to add a field — they add statements:

```
Evaluation:ev-001  →  evaluates     →  Innovation:drip-irrigation-kenya
Evaluation:ev-001  →  usesFramework →  Framework:gender-impact
Evaluation:ev-001  →  score         →  4
Evaluation:ev-001  →  evaluatedBy   →  Person:jane-doe
Evaluation:ev-001  →  date          →  2026-03-15
```

The evaluation is a first-class object, not a field on the innovation. Multiple evaluations from different teams can coexist. This is the "criteria as data, not code" principle.

### What you can do with it

Once information is stored as triples, you can:

- **Query any connection** without pre-building it. "All organisations linked to innovations in Kenya" is one query, not a custom Drupal View.
- **Traverse the graph**. "Innovations → developed by → organisations → partnered with → FAO" is a chain of relationships. The graph follows it naturally. In Drupal, you'd need to build three nested entity-reference lookups.
- **Add new types of information** without schema changes. A new evaluation framework, a new taxonomy, a new type of relationship — all just new triples.
- **Integrate external data**. AGROVOC, TECA, AGRIS, Wikidata — all already published as triples (RDF/linked data). Connecting to them is joining two graphs, not writing an import pipeline.

### What it costs

A knowledge graph requires:

- **An ontology** — a shared agreement on what types of things exist and what relationships connect them. This is the hard work, and it's social, not technical. (See section 4 for how we approach this.)
- **A triple store** — a database optimised for triples. Virtuoso (open source, used by the EU's Joinup/Interoperable Europe Portal) or GraphDB are the standard options. Runs in Docker.
- **Someone who maintains it** — an ontology steward. Without one, you get 500 overlapping properties and the same mess as before, just in a different format.

---

## 2. SPARQL vs. LLM: why structured queries still matter

### The appeal of "just ask the AI"

When people say "let's plug in an agent," they're imagining a user typing a natural-language question and getting a reliable answer. The appeal is obvious: no query language to learn, no schemas to understand, anyone can ask anything.

This is a real and valuable use case, and we should build it (that's what SteveBot is for). But it does not replace structured querying — it *depends* on it.

### What an LLM actually does with your data

An LLM doesn't read your database. It reads *text*. To answer a question about innovations, it needs to:

1. Convert the question into a search over your data.
2. Retrieve the relevant records.
3. Synthesise an answer from those records.

Step 1 is where things break. The LLM has three options:

**Option A: Search over embeddings (vector DB).** The LLM finds text chunks that are semantically similar to the question. Good for: "tell me about drip irrigation." Bad for: "how many innovations in East Africa scored above 3 on climate resilience?" — because counting and filtering require structured data, not text similarity. The LLM will *approximate* the answer, but it may hallucinate numbers, miss records, or include irrelevant ones.

**Option B: Generate a database query (text-to-SQL, text-to-SPARQL).** The LLM writes a SQL or SPARQL query and runs it against the actual database. Good for: any question with precise filters, counts, comparisons, or aggregations. Bad for: vague or exploratory questions. Requires a well-structured schema for the LLM to target.

**Option C: Browse documents in a loop (agentic RAG).** The LLM iteratively searches, reads, and refines. Slow, expensive, and unreliable for anything requiring completeness.

### The key insight

**The knowledge graph makes ALL THREE options better.** It's not knowledge graph *instead of* LLM — it's knowledge graph *underneath* LLM.

- **Option A improves** because the text you embed is richer. Instead of embedding a flat Drupal node with 40 fields (most empty), you embed a paragraph assembled from the graph: "Drip irrigation (Kenya) — developed by ICRISAT, partnered with FAO. Climate resilience: 4/5 (Jane Doe, March 2026). Gender impact: 3/5 (Team B, January 2026). Related: micro-irrigation (Tanzania)." That embedding carries far more useful context.

- **Option B becomes possible** because SPARQL gives the LLM a clean query target. LLMs are actually quite good at generating SPARQL from natural language — the syntax is simpler than SQL, the schema is self-describing (you can query the ontology itself), and every relationship is explicit. This is where the "precise questions" get answered correctly: counts, filters, aggregations, ranked lists. No hallucination, because the answer comes from the database, not from the model's parameters.

- **Option C improves** because the graph gives the agent a structured map to navigate, not a flat sea of documents.

### Concrete comparison

**Question: "Which innovations in semi-arid regions have been evaluated positively by both Team A and Team B?"**

| Approach | What happens | Reliability |
|---|---|---|
| LLM + flat Drupal fields | Vector search for "semi-arid" + "evaluation." Gets some relevant docs, misses others. Can't reliably intersect two teams' evaluations. May hallucinate. | Low |
| LLM + SPARQL generation | LLM generates a SPARQL query that filters by climate zone, joins two evaluation subgraphs, and returns the intersection. Exact answer. | High |
| Pure SPARQL (no LLM) | Same query, written by a human. Exact answer. | Highest |

The point is not that SPARQL replaces the LLM — it's that SPARQL is what the LLM should be *calling*. The LLM is the interface; the knowledge graph is the brain.

### When to use what

| Use case | Best approach |
|---|---|
| "Tell me about innovation X" | LLM + vector retrieval (Option A) |
| "How many innovations in East Africa?" | SPARQL (exact count, no LLM needed) |
| "Which innovations overlap on two evaluation criteria?" | LLM generates SPARQL (Option B) |
| "Summarise the landscape of soil-degradation innovations" | LLM + vector retrieval + graph context |
| "Find me potential partners for a new project on X" | LLM generates SPARQL for graph traversal, then summarises |
| Dashboard / reporting / data visualisation | Direct SPARQL, piped into chart. No LLM involved. |

### The pitch for colleagues

Don't say "SPARQL instead of AI." Say: **"The knowledge graph is what makes the AI reliable. Without structured data underneath, the AI guesses. With the graph, the AI queries."**

---

## 3. Architecture: what to build and in what order

### Three options on the table

**Option A: MVP — Drupal stays as-is, sync outward to a graph**

- Keep the current Drupal portal as the editorial surface.
- Stand up a Virtuoso triple store alongside it.
- Write a sync script (Python, run nightly) that exports innovations from Drupal's JSON:API, maps them to an ontology, and loads triples into Virtuoso.
- Stand up a vector DB (Qdrant/pgvector), populated from the graph.
- SteveBot queries both the vector DB (for natural-language questions) and the SPARQL endpoint (for precise questions).

**Effort:** ~4 weeks for one developer. No changes to Drupal itself.
**Risk:** Low. Drupal is untouched; if the graph doesn't prove useful, you lose nothing.
**Limitation:** Write path is one-way (Drupal → graph). Editing happens in Drupal only. The graph is always slightly stale (nightly sync).

**Option B: Joinup-style twin database**

- Adopt the `sparql_entity_storage` and `rdf_entity` Drupal modules (built by the Joinup team).
- Core entities (innovations, organisations, people) are stored *directly* in Virtuoso as their primary storage. Community content (posts, events, discussions) stays in MySQL.
- Drupal's edit forms, Views, and access control work as normal — the storage layer is abstracted.
- Add CQRS: write to both Virtuoso (primary) and Solr (search index), with Redis caching.
- Public SPARQL endpoint for external queries.

**Effort:** ~3–6 months, requires a developer experienced with Drupal entity storage internals and triple stores.
**Risk:** Medium. Fighting the Open Social distribution's assumptions about MySQL-only storage. Upgrade path becomes complex. Requires understanding of the `sparql_entity_storage` module, which is "minimally maintained" per its Drupal.org page.
**Advantage:** No sync lag. Drupal *is* the graph. Editorial experience is native. Proven at scale by the EU (10+ years, 48,000+ commits).

**Option C: Fork or adopt Joinup as a distribution**

- Take the Joinup Drupal distribution (open source, EUPL-licensed) and adapt it.
- Replace their ADMS-AP ontology with an agricultural-innovation ontology.
- Replace their Collections/Solutions/Releases with Innovations/Organisations/Evaluations.
- Keep their infrastructure: Virtuoso, Solr, Redis, CQRS pattern.

**Effort:** ~6–12 months to adapt. Ongoing effort to maintain the fork against upstream.
**Risk:** High. Joinup's codebase is tightly coupled to EC-specific modules. Separating the semantic-web layer from the EC business logic is non-trivial. If their team changes direction, your fork diverges.
**Advantage:** You get 10 years of solved problems for free. Harvesting/federation, public SPARQL endpoint, content negotiation, dual-database CQRS — all built.

### Which one should we do?

**Start with Option A.** It's the cheapest proof of concept and it validates the hypothesis: "a knowledge graph makes our data more useful." If it doesn't — if the ontology design reveals that nobody agrees on what an innovation is, or if the queries nobody can answer turn out to be queries nobody actually asks — you've spent four weeks, not six months.

If Option A proves the value, **move toward Option B incrementally** — adopt `sparql_entity_storage` for the innovation content type first, leave everything else in MySQL. This is a migration, not a rewrite. You can do it entity type by entity type.

**Option C (Joinup fork) is the right answer if** you were starting from scratch, had a team of 3–4 Drupal developers with semantic web experience, and were building a new portal rather than evolving an existing one. That's not where you are today. But studying their codebase (specifically the entity storage, the CQRS pattern, and the SPARQL endpoint) is valuable regardless of which option you pick.

### Why not just hire the Joinup developers?

You could, and it might be worth exploring. TenForce (the primary contractor, based in Leuven, Belgium) has exactly the expertise you need. But:

- They work primarily through EU procurement frameworks (DIGIT-TM), not direct FAO contracts. Engaging them would require navigating FAO's own procurement process.
- Their expertise is in the Drupal+SPARQL integration layer, not in agricultural innovation domain modelling. You'd still need to do the ontology design and the Open Social integration yourselves.
- A more realistic path: hire a consultant from that ecosystem for 2–3 days to review your architecture and validate the approach. That's cheaper and faster than a full engagement, and gives you confidence before committing.

---

## 4. How does this integrate with Open Social?

### What Open Social does well

Open Social is good at: user profiles, groups, discussions, events, content moderation, notifications, activity streams, and the Challenges group type. These are *community* features. They depend on people, not on structured knowledge.

### What Open Social does NOT need to become

Open Social does not need to become a knowledge graph. The innovation data, the evaluation frameworks, the cross-linked organisations — those belong in the knowledge layer (the graph), not in the community layer (Open Social).

### How they connect

**Today:** The only connection between the STI Portal (ATIO KB) and STI Social (Open Social) is a hyperlink. Users have separate accounts. Content doesn't cross-reference.

**With the proposed architecture:**

1. **Shared identity (Keycloak):** Users log into both systems with one account. A user in Open Social and a person entity in the knowledge graph share a stable identifier. (This is ADR-001, already sketched.)

2. **References, not duplication.** When someone submits an innovation idea through a Challenge in Open Social, the submission references an innovation entity in the knowledge graph by ID. The discussion, voting, and social activity happen in Open Social. The structured data about the innovation (properties, evaluations, relationships) lives in the graph.

3. **Open Social's GraphQL API is the bridge for reads.** A frontend (or SteveBot) that needs both community data ("who commented on this innovation?") and knowledge data ("what's this innovation's climate resilience score?") can query Open Social's GraphQL and the knowledge graph's SPARQL endpoint, either separately or through a future federation layer.

4. **Evaluations flow from Social to the graph.** When a Challenge evaluation is completed in Open Social, the scores are written as triples to the knowledge graph. The evaluation becomes a first-class statement about the innovation — queryable, comparable across frameworks, available to SteveBot. This is the specific integration point where the Challenges feature and the knowledge graph meet.

### What changes in Open Social: almost nothing

Open Social keeps doing what it does. The changes are:

- Add an entity-reference field on relevant content types (e.g., Challenge submissions) that points to a knowledge-graph entity by URI or ID.
- When an evaluation is completed, fire a webhook or write a record that the sync pipeline picks up and translates into triples.
- Authenticate via Keycloak instead of local accounts.

No changes to Open Social's database, no triple store inside Open Social, no SPARQL. The graph is a separate system that Open Social talks to through lightweight integration points.

### A picture of how the pieces fit

```
┌─────────────────────┐   ┌─────────────────────┐
│     Open Social      │   │    STI Portal (KB)   │
│  (community layer)   │   │  (editorial surface) │
│                      │   │                      │
│  - Groups            │   │  - Innovation CRUD   │
│  - Challenges        │   │  - Org/person CRUD   │
│  - Discussions       │   │  - Content access    │
│  - User profiles     │   │                      │
│                      │   │                      │
│  [MySQL + GraphQL]   │   │  [MySQL / Virtuoso]  │
└──────────┬───────────┘   └──────────┬───────────┘
           │                          │
           │    ┌─────────────┐       │
           ├────┤  Keycloak   ├───────┤  ← shared identity
           │    └─────────────┘       │
           │                          │
           │    ┌─────────────────────┤
           │    │  Sync / ETL         │  ← nightly for MVP;
           │    │  (Python cron)      │     native storage later
           │    └──────────┬──────────┘
           │               │
           │    ┌──────────▼──────────┐
           │    │  Knowledge Graph    │
           │    │  (Virtuoso)         │  ← SPARQL endpoint
           │    │                     │
           │    │  Innovations,       │
           │    │  Organisations,     │
           │    │  People,            │
           │    │  Evaluations,       │
           │    │  AGROVOC terms      │
           │    └──────────┬──────────┘
           │               │
           │    ┌──────────▼──────────┐
           │    │  Vector DB          │
           │    │  (Qdrant/pgvector)  │  ← embeddings for SteveBot
           │    └──────────┬──────────┘
           │               │
           └───────┐  ┌────┘
                   │  │
              ┌────▼──▼────┐
              │  SteveBot   │  ← queries vector DB (fuzzy)
              │  (AI chat)  │     + SPARQL (precise)
              └─────────────┘     + Open Social GraphQL (community)
```

---

## 5. Summary: the one-paragraph pitch

The STI portal's data is currently locked in rigid Drupal content types that can't handle the flexibility our teams need or the cross-cutting queries our users want. A knowledge graph stores the same information as connected, queryable triples — flexible enough for different teams to add their own evaluation frameworks without developer intervention, structured enough for precise queries that an LLM can't reliably answer alone. The graph doesn't replace Drupal or Open Social — it sits underneath them as a shared knowledge layer. We can prove this with a four-week MVP that syncs our existing data into a triple store, with zero changes to the current systems. If it works, we have a path to a proper architecture. If it doesn't, we've lost a month.
