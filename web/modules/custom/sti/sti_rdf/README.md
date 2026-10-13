# EAV-based collection search

This add-on to `sti_rdf` lets `/collection/{collection}` render a faceted
search whose filter set is derived at request time from the data, not from
per-collection views config.

## Why this exists

Collections in the STI portal carry *heterogeneous* annotation schemas — one
collection might annotate solutions with 5 fields, another with 50, and a
field with the same machine name in two collections may mean different
things. Maintaining a Views config + Facets config per collection doesn't
scale to hundreds of collections; auto-generating those configs creates an
ongoing maintenance liability. So instead:

  - Every annotation node is exploded on save into rows in a single EAV
    table `annotation_value` keyed by `(collection_id, field_name, value_key)`.
  - The collection search controller queries that table for its schema, its
    facet options, and the matching target entity ids.
  - Adding a new collection is a data operation: index some annotations,
    visit the URL.

## What was added

```
src/Entity/AnnotationValue.php
src/Entity/Storage/AnnotationValueStorageSchema.php
src/Service/AnnotationIndexer.php
src/Service/AnnotationLibrary.php
src/Controller/CollectionSearchController.php
src/Form/CollectionSearchForm.php
sti_rdf.module
config/install/sti_rdf.settings.yml
config/schema/sti_rdf.schema.yml
```

And updated:

```
sti_rdf.info.yml          (adds 'drupal:node' dependency)
sti_rdf.services.yml      (registers indexer + library)
sti_rdf.routing.yml       (adds /collection/{c} and /admin/sti-rdf/reindex)
```

## Wiring up a new collection

`sti_rdf.settings.yml` registers annotation node bundles. The shipped default
covers `dah_annotation`:

```yaml
annotation_types:
  dah_annotation:
    target_field: field_solution
    collection_static: dah
```

Adding a new bundle:

```yaml
annotation_types:
  dah_annotation:
    target_field: field_solution
    collection_static: dah
  agob_annotation:
    target_field: field_target_solution
    # Look up the collection on each annotation via this entity ref field.
    collection_field: field_collection
    # And use this property of the referenced collection node as the id.
    # If omitted, the entity id is used.
    collection_id_property: field_machine_name
    ignored_fields:
      - field_internal_notes
```

After editing this config and importing it, hit `/admin/sti-rdf/reindex`
once to rebuild the EAV table.

On every annotation save/update/delete, `sti_rdf.module`'s entity hooks
re-explode that single annotation's rows. No full rebuild needed for
ordinary edits.

## The data model

`annotation_value` has indexes on:

  - `(collection_id, field_name)`              — schema discovery
  - `(collection_id, field_name, value_key)`   — filter resolution
  - `(annotation_id)`                          — clear-on-update
  - `(target_entity_type, target_entity_id)`   — reverse lookup

Each annotated value writes one row. For an annotation with 20 fields, ~3
values each, that's ~60 rows. Total volume = (#annotations × avg fields ×
avg cardinality). For hundreds of collections × thousands of annotations
× small cardinalities, you're in the low millions of rows max — well
within InnoDB's comfort zone on these indexes.

## URL collisions

`/collection/{collection}` is a guess at the URL you want. If your site
already has nodes or path aliases at that path, this route will collide. Two
options:

  - Change `path:` in `sti_rdf.routing.yml` to e.g.
    `/collection/{collection}/search` and link to it from the existing
    collection node's template, or
  - Drop the existing collection node entity and let this controller own
    the path.

## What it deliberately doesn't do

  - **No full-text search.** The query is pure facet selection. If you need
    full-text against the target entities, wire a Search API index over
    *solutions* and intersect its result set with
    `AnnotationLibrary::findTargetEntityIds()`. Easy to add later.
  - **No theming polish.** Results render as `item_list` of entity teasers
    with a tiny prev/next pager. Theme as needed.
  - **No facet UI features beyond checkboxes.** No range sliders, no
    hierarchy widgets, no "show more / less". These can be added per-field
    if specific collections need them; the data layer already supports them.
  - **No admin UI for `sti_rdf.settings`.** Edit the YAML and import. A
    config form is straightforward to add if you want one.

## Testing it

```
# 1. Enable / re-enable the module so the entity schema gets created.
drush en sti_rdf -y
drush updb -y

# 2. Backfill from any existing dah_annotation nodes.
# (or visit /admin/sti-rdf/reindex)
drush ev "echo \Drupal::service('sti_rdf.annotation_indexer')->reindexAll();"

# 3. Visit the search page.
open http://your-site/collection/dah
```

## Known issues from the existing module

While reading the code I noticed (unchanged here):

  - `src/Plugin/rdf_sync/RdfUriGenerator/StiRdfUriGenerator.php` has a stale
    namespace (`Drupal\joinup_rdf\…`), a mismatched class name
    (`JoinupRdfUriGenerator`), and references a deriver that isn't in this
    module. The plugin id `joinup_po_namespace` won't match what
    `node.type.dah_annotation.yml` asks for (`sti_namespace`). This file
    won't load as-is. Wanted to flag it; left alone.
  - `field_solution` targets `solution` bundles, but the only node type in
    `config/install/` is `innovation`. The normalize subscriber will silently
    skip if the reference can't be resolved, so this is probably fine in
    practice but worth confirming the bundle name is what you mean.
