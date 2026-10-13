# STI graph explorer (`sti_graph_explorer`)

An interactive d3 page at `/explore/graph` for looking at what `rdf_sync` has
projected into the triplestore. It renders in the front-end theme (`fao_lean`).

- **Schema view.** Classes are drawn as circles sized by instance count and
  coloured by namespace. Arrows run from a class to the class of its
  predicates' values. The side panel lists each class's links, literal fields,
  datatypes and language-tag use, and lets you browse its instances.
- **Explore view.** Starts from one resource and adds neighbours on
  double-click (up to 40 per expansion). The panel lists every literal and
  link of the selected node, can add any one relation to the canvas, and
  links to the Drupal entity behind the IRI.

The URL hash keeps the view: `#class=<iri>` or `#explore=<iri>` can be
bookmarked or shared.

## Install

```sh
ddev drush pm:install sti_graph_explorer -y
ddev drush role:perm:add 'ROLE' 'explore sti graph'
ddev drush cex -y   # then commit config/sync
```

It reads through Joinup's `sparql_endpoint.readonly_endpoint`, which connects
as Virtuoso's `SPARQL_RO` user over `/sparql-auth`. Check
*Reports → Status report → SPARQL read-only endpoint* first. If that entry
shows an error, the page will too.

## How it is built

| Piece | Role |
|---|---|
| `Sparql\QueryFactory` | The only queries that run. Inputs are a validated `Iri`, an escaped string literal, or a clamped integer. |
| `Sparql\Iri` | Rejects anything that could end or extend `<…>` in SPARQL. |
| `Sparql\ResultParser` | SPARQL JSON → rows; detects truncated results (below). |
| `Sparql\SparqlClient` | Read-only endpoint → parser; transport errors become `SparqlQueryException`. |
| `GraphExplorer` | Schema (cached), instance pages, resource neighbourhoods, IRI → Drupal entity. |
| `Controller\ExplorerController` | Page shell and three JSON endpoints: 400 for bad input, 502 (logged) for store failure. |
| `js/graph-explorer.js` | d3 drawing and the side panel. Data is written as text only, never as HTML. |
| `vendor/d3` | d3 7.9.0, vendored because `fao_lean` serves no third-party JS. |

## Silent failure modes, and what guards them

| If this breaks… | …the page would show | Guard |
|---|---|---|
| Virtuoso hits `MaxQueryExecutionTime` | Truncated counts presented as complete (Virtuoso answers 200 and marks the cut only in `X-SQL-State`) | `ResultParser` reads the header; the page shows a warning; a partial schema is **not cached**. Tests: `ResultParserTest`, `GraphExplorerTest`. |
| Type × label rows not collapsed | Every count and list inflated | `GraphExplorer::shapeResource()`. Test: `GraphExplorerTest`. |
| Input reaches a query unescaped | A different query answering 200 | `Iri`, `QueryFactory::literal()`. Test: `QueryInputTest`. |

```sh
ddev exec 'vendor/bin/phpunit -c web/core web/modules/custom/sti_graph_explorer'
```

The tests are unit tests over core interfaces only; they need neither Joinup
nor a triplestore.

## Known limits

- **Cost of the schema.** The schema queries scan the whole graph (about 4M
  triples today). The result is cached for an hour and not invalidated by
  `rdf_sync` writes; *Refresh schema* recomputes it. At 200 collections the
  scan may hit Virtuoso's 120 s limit. The page then says the result is
  partial, and the fix is to raise the limit or precompute the schema on cron.
- **Access.** Neither the graph nor this page knows about Drupal node access or
  OG. The permission is marked *restrict access*. Only the "Open in Drupal"
  link is access-checked.
- **Coupling.** `sparql_endpoint` depends on the distribution (`joinup_rdf`).
  The explorer uses only its one-method interface, so swapping in another
  read-only client is a one-service change in `sti_graph_explorer.services.yml`.
- **Accessibility.** The drawing is not accessible on its own. The side panel
  carries the same information as text and buttons, and nodes are keyboard
  focusable.
