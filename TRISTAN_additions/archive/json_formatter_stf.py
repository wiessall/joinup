#!/usr/bin/env python3
"""
stf_normalize.py — Seeding the Future → IRD JSON schema

To adapt for a new collection: change only CONFIG.
Everything below the CONFIG block is generic and should not need editing.
"""
import csv
import json
import argparse
from datetime import datetime
from pathlib import Path

# ── Per-collection config ─────────────────────────────────────────────────────
CONFIG = {
    # Collection identity (can be overridden by CLI args)
    'collection_id':    'stf',
    'collection_label': 'Seeding the Future',

    # Core field mapping: std_field → (csv_column, list_separator | None)
    # Omit a std_field entirely to leave it as None in output.
    # list_separator: string used to split multi-value cells, e.g. '|' or ','
    'core': {
        'id':           ('ID',                            None),
        'title':        ('Project Title',                 None),
        'description':  ('Innovation Description',        None),
        'abstract':     ('Executive Summary',             None),
        'url':          ('Website',                       None),
        'date':         ('Created date',                  None),
        'owner_name':   ('Submitting Organization',       None),
        'country':      ('Country',                       None),
        'domain_names': ('Innovation Domains Addressed',  '|'),
        # Not present in source: language_names, contact_name,
        #                        classification_name, topic_names
    },

    # Annotation columns that are multi-value: csv_column → separator
    # Columns not listed here are treated as plain strings.
    'annotation_lists': {
        'Benefitting Country':                          '|',
        'SDGs Addressed':                               '|',
        'Domain':                                       '|',
        'Areas_of_Focus':                               '|',
        'Areas_of_Need':                                '|',
        'Areas_of_Expertise':                           '|',
        'Inclusion and Representation':                 '|',
        'Prior Awards or Recognitions':                 '|',
        'Network Contact Emails or LinkedIN Profiles':  '|',
        'human_impact_in_1-5_years':                    '|',
        'human_impact_in_6-10_years':                   '|',
        'human_impact_in_11-15_years':                  '|',
        'Greenhouse Gasses_impact':                     '|',
        'Water Use_impact':                             '|',
        'Land Use_impact':                              '|',
        'Biodiversity_impact':                          '|',
        'Phosphorus Use_impact':                        '|',
        'Nitrogen Use_impact':                          '|',
    },

    # Skip a record if any of these std_fields resolve to empty/None
    'required': ['title'],

    # Owner extraction — fields to pull from each CSV row into the owners JSON.
    # Compatible with the ird_owner_import migration template.
    # title is derived from owner_name (already in core), not re-mapped here.
    # as_list=True → wrap as [{"value": "..."}] (required by sub_process plugin)
    'owner_fields': {
        'country':    ('Country',            False),
        'link':       ('Website',            False),
        'owner_type': ('Organization Type',  True),
        # description, acronym: not available in STF source
    },
    'owner_id_prefix': 'stf',
}
# ─────────────────────────────────────────────────────────────────────────────


def parse_list(value: str, sep: str) -> list:
    """Split a delimited string into IRD list format [{value: v}, ...]."""
    if not value or not value.strip():
        return []
    return [{"value": v.strip()} for v in value.split(sep) if v.strip()]


def clean(value: str) -> str | None:
    """Strip whitespace; return None for empty strings."""
    v = value.strip() if value else ''
    return v or None


def clean_url(value: str) -> str | None:
    """
    Normalize a URL for use as a URI in RDF/SPARQL:
      - Truncate at first space (catches concatenated junk from dirty source data)
      - Add https:// if no scheme present
      - Return None if result has no dot (not URL-like)
    """
    v = value.strip() if value else ''
    if not v:
        return None
    v = v.split()[0]                                   # drop anything after first space
    if not v.startswith(('http://', 'https://')):
        v = 'https://' + v
    if '.' not in v.split('://', 1)[-1]:               # no dot → not a real URL
        return None
    return v


def slugify(text: str) -> str:
    """Derive a stable ID-safe slug from a string."""
    import re
    return re.sub(r'[^a-z0-9]+', '-', text.lower()).strip('-')


def extract_owners(records: list[dict], cfg: dict, raw_rows: list[dict]) -> list[dict]:
    """
    Deduplicate orgs across all records and build owner JSON compatible with
    the ird_owner_import migration template (entity:node / owner bundle).

    owner_type uses list format [{"value": "..."}] as required by sub_process.
    id is a derived slug — stable as long as the org name doesn't change.
    """
    owner_fields = cfg.get('owner_fields', {})
    prefix       = cfg.get('owner_id_prefix', cfg.get('collection_id', 'unknown'))
    name_to_row  = {r.get('owner_name'): row
                    for r, row in zip(records, raw_rows)
                    if r.get('owner_name')}
    seen = {}
    for record in records:
        name = record.get('owner_name')
        if not name or name in seen:
            continue
        row = name_to_row.get(name, {})
        owner = {
            'id':          f"{prefix}_{slugify(name)}",
            'title':       name,
            'description': None,
            'acronym':     None,
            'country':     None,
            'link':        None,
            'owner_type':  [],
        }
        for field, (csv_col, as_list) in owner_fields.items():
            raw = row.get(csv_col, '')
            if not raw or not raw.strip():
                continue
            if as_list:
                owner[field] = [{'value': raw.strip()}]
            elif field == 'link':
                owner[field] = clean_url(raw)
            else:
                owner[field] = clean(raw)
        seen[name] = owner
    return list(seen.values())


def normalize(source: Path, cfg: dict) -> tuple[list[dict], list[dict]]:
    core_map       = cfg['core']
    ann_lists      = cfg.get('annotation_lists', {})
    required       = cfg.get('required', ['title'])
    core_csv_cols  = {csv_col for _, (csv_col, _) in core_map.items()}

    # Fixed std_fields that may not be in source — always present in output
    defaults = {
        'language_names': [],
        'contact_name':   None,
    }

    records  = []
    raw_rows = []
    skipped  = 0
    prefix   = cfg.get('owner_id_prefix', cfg.get('collection_id', 'unknown'))

    with open(source, newline='', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        reader.fieldnames = [k.strip().strip('"') for k in reader.fieldnames]
        all_cols = reader.fieldnames

        unmapped = [c for c in all_cols if c not in core_csv_cols]
        print(f"  Core columns mapped : {len(core_csv_cols)}")
        print(f"  Annotation columns  : {len(unmapped)}")

        for row in reader:
            record = dict(defaults)

            # Map core fields
            for std_field, (csv_col, sep) in core_map.items():
                raw = row.get(csv_col, '')
                if sep:
                    record[std_field] = parse_list(raw, sep)
                elif std_field == 'url':
                    record[std_field] = clean_url(raw)
                else:
                    record[std_field] = clean(raw)

            # Skip if required fields are missing
            if any(not record.get(f) for f in required):
                skipped += 1
                continue

            # Stamp owner_id so the innovation migration can do migration_lookup
            if record.get('owner_name'):
                record['owner_id'] = f"{prefix}_{slugify(record['owner_name'])}"

            # Annotations: all non-core columns that have a value
            record['_annotations'] = {}
            for col in unmapped:
                raw = row.get(col, '')
                if not raw or not raw.strip():
                    continue
                sep = ann_lists.get(col)
                record['_annotations'][col] = (
                    parse_list(raw, sep) if sep else clean(raw)
                )

            record['_raw'] = {k: v for k, v in row.items() if v and v.strip()}
            records.append(record)
            raw_rows.append(row)

    if skipped:
        print(f"  Skipped (missing required fields): {skipped}")

    return records, raw_rows


def main():
    parser = argparse.ArgumentParser(
        description='Normalize a CSV to IRD JSON schema. '
                    'Edit CONFIG at top of file to adapt to a new collection.'
    )
    parser.add_argument('source', help='Input CSV file')
    parser.add_argument('output', help='Output JSON file')
    parser.add_argument('--collection-id',    default=CONFIG['collection_id'])
    parser.add_argument('--collection-label', default=CONFIG['collection_label'])
    parser.add_argument('--og-audience-nid',  type=int, default=0)
    parser.add_argument('--owner-nid',        type=int, default=0)
    parser.add_argument('--contact-nid',      type=int, default=0)
    args = parser.parse_args()

    print(f"\nNormalizing: {args.source}")
    records, raw_rows = normalize(Path(args.source), CONFIG)
    owners            = extract_owners(records, CONFIG, raw_rows)

    # ── Innovations JSON ──────────────────────────────────────────────────────
    out = {
        'meta': {
            'collection_id':    args.collection_id,
            'collection_label': args.collection_label,
            'og_audience_nid':  args.og_audience_nid,
            'owner_nid':        args.owner_nid,
            'contact_nid':      args.contact_nid,
            'record_count':     len(records),
            'export_timestamp': datetime.now().isoformat(),
        },
        'records': records,
    }
    Path(args.output).write_text(
        json.dumps(out, indent=2, ensure_ascii=False), encoding='utf-8'
    )
    print(f"\n✓ {len(records)} records → {args.output}")

    # ── Owners JSON ───────────────────────────────────────────────────────────
    owners_path = Path(args.output).with_stem(Path(args.output).stem + '_owners')
    owners_out  = {
        'meta': {
            'collection_id':    args.collection_id,
            'collection_label': args.collection_label,
            'record_count':     len(owners),
            'export_timestamp': datetime.now().isoformat(),
        },
        'records': owners,
    }
    owners_path.write_text(
        json.dumps(owners_out, indent=2, ensure_ascii=False), encoding='utf-8'
    )
    print(f"✓ {len(owners)} owners  → {owners_path}")

    if records:
        print("\nFirst record — core field coverage:")
        s = records[0]
        all_core = list(CONFIG['core'].keys()) + ['language_names', 'contact_name']
        for f in all_core:
            val = s.get(f)
            present = bool(val) if not isinstance(val, list) else len(val) > 0
            print(f"  {'✓' if present else '✗'} {f}")
        print(f"  {len(s['_annotations'])} annotation keys: "
              f"{', '.join(list(s['_annotations'].keys())[:6])}{'...' if len(s['_annotations']) > 6 else ''}")


if __name__ == '__main__':
    main()