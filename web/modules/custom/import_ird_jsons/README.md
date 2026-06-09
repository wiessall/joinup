# IRD Universal Solution Importer

## Architecture

```
CSV / API / JSON (any source)
       │
       ▼
   IRD lib (Python)
   ┌─────────────────────────────────┐
   │ - Reads source-specific schema  │
   │ - Normalizes to common JSON     │
   │ - Flattens annotations          │
   │ - Preserves _raw original       │
   └──────────┬──────────────────────┘
              │
              ▼
   normalized JSON (one per collection per run)
   ├── car.json          ← records[] with core fields
   └── car_annotations.json  ← flattened property/value rows
              │
              ▼
   Drupal Migrate (two migrations, never changes)
   ├── ird_solution_import     ← reads records[], creates solution nodes
   └── ird_annotation_import   ← reads annotations[], links to solutions
              │
              ▼
   solution nodes + annotation nodes in Drupal
              │
              ▼
   rdf_sync → Virtuoso (if enabled)
```

## Files

| File | Purpose |
|------|---------|
| `normalized-schema.example.json` | Contract: what IRD lib must produce |
| `migrate_plus.migration.ird_solution_import.yml` | The one solution migration |
| `migrate_plus.migration.ird_annotation_import.yml` | Companion annotation migration |

## Switching collections

The migration YAMLs never change. What changes per collection:

1. **The JSON file** — IRD lib produces `dah.json`, `dvi.json`, etc.
2. **The constants** — `OG_AUDIENCE_NID`, `OWNER_NID`, `CONTACT_NID`,
   and default taxonomy TIDs.

### Option A: Edit constants in YAML (simplest for prototype)

```bash
# 1. Edit the YAML: change urls and constants
vim migrate_plus.migration.ird_solution_import.yml

# 2. Re-register
drush cr
drush migrate:import ird_solution_import --update
drush migrate:import ird_annotation_import --update
```

### Option B: Override at runtime (production)

Write a thin drush command or shell wrapper:

```bash
#!/bin/bash
# import-collection.sh <collection_id>
COLLECTION=$1
drush migrate:import ird_solution_import \
  --source-url="modules/custom/import_IRD_jsons/sources/${COLLECTION}.json"
drush migrate:import ird_annotation_import \
  --source-url="modules/custom/import_IRD_jsons/sources/${COLLECTION}_annotations.json"
```

### Option C: Meta-driven constants (roadmap)

Write a custom source plugin that reads `meta` from the JSON and
injects `OG_AUDIENCE_NID` etc. automatically. Then the JSON is
fully self-describing and the YAML is truly universal.

## Prerequisites

Before importing a collection:

1. **Collection node must exist** — `og_audience` points to it
2. **Owner node must exist** — at least a default org
3. **Contact node must exist** — at least a default
4. **Taxonomy vocabularies seeded:**
   - `solution_classification` — at least one term
   - `policy_domain` — at least one term
   - `language` — at least "English"
   - `topic` — can be empty if source provides names (entity_generate creates them)

## What IRD lib must do per collection

| Collection | Source shape | Notes |
|------------|-------------|-------|
| CAR | CSV with `project_id`, `description_of_innovation`, `type_of_innovation`, `date_of_collection` | Minimal. No annotations expected. |
| DAH | JSON API with `solutions[]`, each having `organisation`, `countries[]`, `useCases[]`, `technologies[]` | Rich annotations. Owner comes from data. |
| DVI | CSV with villages, initiatives, solutions as separate entities | Needs pre-processing: village/initiative → separate entity types or annotations |
| FAORNE | TBD | TBD |
| ECA Agritech | TBD | TBD |

## Annotation content type

Create `innovation_annotation` with these fields:

```
field_innovation   entity_reference → node (solution)   required
field_collection   entity_reference → node (collection) required
field_property     string                                required
field_value        text_long                             required
field_state        state                                 required
```

Or, per our earlier discussion: use bundle-per-collection
(`dah_annotation`, `dvi_annotation`) with real field names instead
of generic property/value. The migration stays the same — just
change `default_bundle` in the annotation migration's destination.

## Known limitations

- `null_coalesce` requires migrate_plus 6.x; check your version
- `entity_generate` creates taxonomy terms on the fly — review
  after import for duplicates / casing issues
- `overwrite_properties` in destination only protects listed fields;
  a full `--update` will overwrite everything else
- `field_abstract` truncation at 250 chars is a safety margin;
  actual field max is 255
- Owner lookup by `title` is fragile if org names vary across
  collections — normalize in IRD lib before export
