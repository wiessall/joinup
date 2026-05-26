#! /home/tristan/micromamba/envs/atio/bin/python3.13
"""
ird_normalize.py — IRD collection normalizer  (merged v2)

Reads CSV / TSV / Excel / JSON, maps source columns to the IRD core schema,
assigns the rest to _annotations, and writes the normalized JSON files the
migration + ird:annotate pipeline expects.

Modes
─────
  Batch (existing config):
      ird_normalize.py source.csv out.json --config stf.yml

  Interactive (new collection, saves config for next run):
      ird_normalize.py source.csv out.json --save-config new_coll.yml

  Headless (all columns → _annotations, no mapping):
      ird_normalize.py source.csv out.json --no-interactive

Config format
─────────────
  See the YAML template written by --save-config, or the inline EXAMPLE_CONFIG
  constant below.  One rule: every key under `core` and `annotations` uses the
  ORIGINAL source column name (post header-normalisation) as the config key,
  not the IRD field name (which goes in the `field` sub-key for annotations).
"""
from __future__ import annotations

import re
import json
import argparse
import sys
from datetime import datetime
from pathlib import Path

import pandas as pd

try:
    import yaml
    HAS_YAML = True
except ImportError:
    HAS_YAML = False

# =============================================================================
# Core schema — the fields the migration YAML expects on every innovation node.
# Anything in the source that is NOT mapped here lands in _annotations.
# =============================================================================
CORE_FIELDS = {
    "id":                  "Record ID (unique, required)",
    "title":               "Title (required)",
    "description":         "Full description (HTML ok)",
    "abstract":            "Short summary / executive summary",
    "date":                "Date (YYYY-MM-DD preferred)",
    "url":                 "Landing page URL",
    "classification_name": "Classification / innovation type",
    "domain_names":        "Domain(s) [list]",
    "topic_names":         "Topic(s) [list]",
    "language_names":      "Language(s) [list]",
    "owner_name":          "Owner organisation name",
    "contact_name":        "Contact person name",
}
# Fields the migration handles as multi-value arrays fed through sub_process.
ARRAY_CORE_FIELDS = {"domain_names", "topic_names", "language_names"}

# Defaults inserted for every record even when the source has no value.
CORE_DEFAULTS: dict = {
    "language_names": [],
    "contact_name":   None,
}

# Example YAML config snippet printed by --help-config.
EXAMPLE_CONFIG = """
collection_id:    stf
collection_label: Seeding the Future
owner_id_prefix:  stf          # prefix for generated owner IDs
required:         [title]       # skip row if any of these are empty

core:
  id:           { column: ID }
  title:        { column: "Project Title" }
  description:  { column: "Innovation Description" }
  abstract:     { column: "Executive Summary" }
  url:          { column: Website,  type: url }
  date:         { column: "Created date" }
  owner_name:   { column: "Submitting Organization" }
  country:      { column: Country }
  domain_names:
    column:       "Innovation Domains Addressed"
    separator:    "|"
    strip_prefix: '^\\d+\\.\\s*'   # strips "1. ", "2. " ordinal labels

owner_fields:
  country:    { column: Country }
  link:       { column: Website, type: url }
  owner_type: { column: "Organization Type", as_list: true }

# Per-column annotation overrides.
# Columns not listed here are treated as plain strings.
# group_as: merge multiple source columns into one annotation key.
annotations:
  "Benefitting Country":
    separator: "|"
  "SDGs Addressed":
    separator: "|"
    strip_prefix: '^\\d+\\.\\s*'
  "Areas_of_Need":
    separator: "|"
  "Areas_of_Expertise":
    separator: "|"
  # Multi-column grouping example:
  # "Domain":      { separator: "|", group_as: thematic_area }
  # "Sub-domain":  { separator: "|", group_as: thematic_area }
"""


# =============================================================================
# Helpers
# =============================================================================

def normalize_header(h: str) -> str:
    """
    Clean a raw CSV column header.
    - Strips BOM, leading/trailing whitespace and quotes.
    - Multi-line headers (common in template CSVs): keeps only the first line.
      e.g. "Name of Solution\n(enter text here)" → "Name of Solution"
    """
    h = h.strip().strip('"').strip("'").lstrip('\ufeff')
    first_line = h.splitlines()[0].strip()
    return first_line


def slugify(text: str) -> str:
    return re.sub(r'[^a-z0-9]+', '-', text.lower()).strip('-')


def clean(value) -> str | None:
    if value is None:
        return None
    try:
        if pd.isna(value):
            return None
    except (TypeError, ValueError):
        pass
    v = str(value).strip()
    return v or None


def clean_url(value) -> str | None:
    """Normalise a URL: add scheme if missing, drop junk after first space."""
    v = str(value).strip() if value else ''
    if not v:
        return None
    try:
        if pd.isna(value):
            return None
    except (TypeError, ValueError):
        pass
    v = v.split()[0]
    if not v.startswith(('http://', 'https://')):
        v = 'https://' + v
    if '.' not in v.split('://', 1)[-1]:
        return None
    return v


def parse_list(raw, sep: str, strip_prefix: str | None = None) -> list[dict]:
    """
    Split a delimited field into IRD list format [ {value: v}, ... ].
    strip_prefix: optional regex applied to each element after splitting,
    useful for ordinal labels like "1. ", "2. " in STF domain fields.
    """
    if raw is None:
        return []
    try:
        if pd.isna(raw):
            return []
    except (TypeError, ValueError):
        pass
    parts = [p.strip() for p in str(raw).split(sep) if p.strip()]
    if strip_prefix:
        parts = [re.sub(strip_prefix, '', p).strip() for p in parts]
    return [{"value": p} for p in parts if p]


def detect_separator(samples: list[str]) -> str | None:
    """
    Heuristic: if most non-empty values contain a consistent delimiter,
    return it.  Checks | first (less ambiguous), then ; then ,.
    """
    non_empty = [s for s in samples if s and str(s).strip()]
    if len(non_empty) < 3:
        return None
    for sep in ('|', ';'):
        hits = sum(1 for s in non_empty if sep in str(s))
        if hits / len(non_empty) >= 0.5:
            return sep
    return None


# =============================================================================
# Config I/O
# =============================================================================

def load_config(path: Path) -> dict:
    if not HAS_YAML:
        sys.exit("pyyaml is required for --config.  Install with: pip install pyyaml")
    with open(path, encoding='utf-8') as f:
        cfg = yaml.safe_load(f)
    cfg.setdefault('required',      ['title'])
    cfg.setdefault('core',          {})
    cfg.setdefault('owner_fields',  {})
    cfg.setdefault('annotations',   {})
    return cfg


def save_config(cfg: dict, path: Path) -> None:
    if not HAS_YAML:
        fallback = path.with_suffix('.json')
        fallback.write_text(json.dumps(cfg, indent=2, ensure_ascii=False))
        print(f"  (pyyaml not installed — saved as JSON: {fallback})")
        return
    with open(path, 'w', encoding='utf-8') as f:
        yaml.dump(cfg, f, allow_unicode=True, sort_keys=False)
    print(f"  Config saved → {path}")


# =============================================================================
# Source loading
# =============================================================================

def load_source(path: Path) -> pd.DataFrame:
    suffix = path.suffix.lower()
    if suffix == '.csv':
        df = pd.read_csv(path, dtype=str, encoding='utf-8-sig')
    elif suffix in ('.tsv', '.txt'):
        df = pd.read_table(path, dtype=str)
    elif suffix in ('.xlsx', '.xls'):
        df = pd.read_excel(path, dtype=str)
    elif suffix == '.json':
        df = pd.read_json(path)
    else:
        sys.exit(f"Unsupported source format: {suffix}")

    df.columns = [normalize_header(str(c)) for c in df.columns]
    df = df.dropna(how='all')
    return df


# =============================================================================
# Interactive mapping
# =============================================================================

def interactive_mapping(df: pd.DataFrame, args) -> dict:
    """
    Walk the user through mapping source columns to core IRD fields.
    Returns a complete config dict.
    """
    cols = list(df.columns)

    print("\n── Source columns " + "─" * 50)
    for i, c in enumerate(cols):
        sample = df[c].dropna().iloc[0] if not df[c].dropna().empty else ''
        sample_str = str(sample)[:60].replace('\n', ' ')
        print(f"  [{i:>3}] {c:<40}  e.g. {sample_str}")

    def pick_col(prompt: str, allow_empty: bool = True) -> str | None:
        while True:
            raw = input(f"\n  {prompt}: ").strip()
            if raw == '':
                return None if allow_empty else pick_col(prompt, allow_empty)
            if raw.isdigit() and int(raw) < len(cols):
                return cols[int(raw)]
            if raw in cols:
                return raw
            print(f"    ✗ '{raw}' not recognised. Enter an index or exact column name.")

    def ask_separator(col_name: str, mapped_col: str) -> tuple[str | None, str | None]:
        """Ask about separator + strip_prefix for a column, with auto-suggestion."""
        samples = df[mapped_col].dropna().tolist()[:20]
        suggested = detect_separator([str(s) for s in samples])
        sep_prompt = f"    Separator for '{col_name}'"
        if suggested:
            sep_prompt += f" (detected '{suggested}', Enter to accept)"
        else:
            sep_prompt += " (Enter to skip)"
        raw_sep = input(sep_prompt + ': ').strip()
        sep = raw_sep if raw_sep else (suggested or None)

        strip = None
        if sep:
            raw_strip = input(
                "    Strip prefix regex (e.g. ^\\d+\\.\\s* to remove '1. ', Enter to skip): "
            ).strip()
            strip = raw_strip or None
        return sep, strip

    print("\n── Core field mapping " + "─" * 45)
    print("Enter column index or name; press Enter to skip.\n")

    cfg: dict = {
        'collection_id':    args.collection_id or input("Collection ID (machine name, e.g. 'stf'): ").strip(),
        'collection_label': args.collection_label or input("Collection label: ").strip(),
        'owner_id_prefix':  '',
        'required':         ['title'],
        'core':             {},
        'owner_fields':     {},
        'annotations':      {},
    }
    cfg['owner_id_prefix'] = cfg['collection_id']

    mapped_source_cols: set[str] = set()

    for field, description in CORE_FIELDS.items():
        mapped_col = pick_col(f"{field}  ({description})")
        if not mapped_col:
            cfg['core'][field] = None
            continue

        field_cfg: dict = {'column': mapped_col}
        mapped_source_cols.add(mapped_col)

        if field == 'url':
            field_cfg['type'] = 'url'
        elif field in ARRAY_CORE_FIELDS:
            sep, strip = ask_separator(field, mapped_col)
            if sep:
                field_cfg['separator'] = sep
            if strip:
                field_cfg['strip_prefix'] = strip

        cfg['core'][field] = field_cfg

    # Annotation columns
    annotation_cols = [c for c in cols if c not in mapped_source_cols]
    if annotation_cols:
        print(f"\n── Annotation columns ({len(annotation_cols)}) " + "─" * 30)
        print("Configure separators for multi-value columns (Enter to skip each).\n")
        for col in annotation_cols:
            samples = df[col].dropna().tolist()[:20]
            if not samples:
                continue
            suggested = detect_separator([str(s) for s in samples])
            if suggested:
                raw = input(
                    f"  '{col}' — detected sep '{suggested}'. "
                    f"Accept [Y/n] or enter different sep: "
                ).strip()
                sep = suggested if (raw.lower() in ('', 'y', 'yes')) else (raw or None)
            else:
                raw = input(f"  '{col}' — separator? (Enter to skip): ").strip()
                sep = raw or None

            if sep:
                ann_cfg: dict = {'separator': sep}
                raw_strip = input(
                    f"    Strip prefix regex for '{col}'? (Enter to skip): "
                ).strip()
                if raw_strip:
                    ann_cfg['strip_prefix'] = raw_strip

                raw_group = input(
                    f"    Group-as key (merge into single annotation key, Enter to skip): "
                ).strip()
                if raw_group:
                    ann_cfg['group_as'] = raw_group

                cfg['annotations'][col] = ann_cfg

    # Owner fields
    print("\n── Owner fields (for organizations JSON) " + "─" * 25)
    print("These build the companion owners JSON for the ird_owner_import migration.\n")
    for field, hint in [
        ('country', 'Country of the org'),
        ('link',    'Website URL'),
        ('owner_type', 'Org type (will be wrapped as [{value:...}])'),
        ('description', 'Org description'),
        ('acronym',     'Acronym'),
    ]:
        mapped_col = pick_col(f"owner.{field}  ({hint})")
        if mapped_col:
            owner_cfg: dict = {'column': mapped_col}
            if field == 'link':
                owner_cfg['type'] = 'url'
            elif field == 'owner_type':
                owner_cfg['as_list'] = True
            cfg['owner_fields'][field] = owner_cfg

    return cfg


# =============================================================================
# Record building
# =============================================================================

def parse_field(raw, field_cfg: dict | None) -> object:
    """Apply field-level config (separator, strip_prefix, type) to a raw value."""
    if field_cfg is None:
        return None
    if isinstance(field_cfg, str):
        # bare column name shorthand (shouldn't normally happen)
        return clean(raw)

    sep   = field_cfg.get('separator')
    strip = field_cfg.get('strip_prefix')
    ftype = field_cfg.get('type')

    if ftype == 'url':
        return clean_url(raw)
    if sep:
        return parse_list(raw, sep, strip)
    return clean(raw)


def build_records(df: pd.DataFrame, cfg: dict) -> tuple[list[dict], list[dict]]:
    core_map       = {f: c for f, c in cfg.get('core', {}).items() if c}
    ann_overrides  = cfg.get('annotations', {})
    required       = cfg.get('required', ['title'])
    prefix         = cfg.get('owner_id_prefix') or cfg.get('collection_id', 'unknown')

    # Source columns claimed by core mappings
    core_source_cols = {v['column'] if isinstance(v, dict) else v
                        for v in core_map.values() if v}

    annotation_cols = [c for c in df.columns if c not in core_source_cols]

    # group_as merging: map source_col → group key
    group_as_map = {
        col: ann_cfg.get('group_as', col)
        for col, ann_cfg in ann_overrides.items()
        if isinstance(ann_cfg, dict) and 'group_as' in ann_cfg
    }

    records:  list[dict] = []
    raw_rows: list[dict] = []
    skipped = 0

    for _, row in df.iterrows():
        record: dict = dict(CORE_DEFAULTS)

        # ── Core fields ───────────────────────────────────────────────────────
        for std_field, field_cfg in core_map.items():
            col = field_cfg['column'] if isinstance(field_cfg, dict) else field_cfg
            raw = row.get(col, '')
            record[std_field] = parse_field(raw, field_cfg if isinstance(field_cfg, dict) else None)

        # ── Required gate ─────────────────────────────────────────────────────
        if any(not record.get(f) for f in required):
            skipped += 1
            continue

        # ── Owner ID (for migration_lookup in INNOVATION migration) ───────────
        if record.get('owner_name'):
            record['owner_id'] = f"{prefix}_{slugify(record['owner_name'])}"

        # ── Annotations ───────────────────────────────────────────────────────
        # Grouped columns are merged into a single key; others use column name.
        annotations: dict = {}
        for col in annotation_cols:
            raw = row.get(col, '')
            if raw is None or (isinstance(raw, float) and pd.isna(raw)):
                continue
            raw_str = str(raw).strip()
            if not raw_str or raw_str.lower() in ('nan', 'none', ''):
                continue

            ann_cfg = ann_overrides.get(col, {})
            if not isinstance(ann_cfg, dict):
                ann_cfg = {}

            sep   = ann_cfg.get('separator')
            strip = ann_cfg.get('strip_prefix')
            value = parse_list(raw_str, sep, strip) if sep else raw_str
            key   = group_as_map.get(col, col)

            # Merge into existing group
            if key in annotations and isinstance(annotations[key], list) and isinstance(value, list):
                annotations[key].extend(value)
            else:
                annotations[key] = value

        record['_annotations'] = annotations
        record['_raw'] = {
            k: (None if (isinstance(v, float) and pd.isna(v)) else v)
            for k, v in row.items()
        }
        records.append(record)
        raw_rows.append(dict(row))

    if skipped:
        print(f"  Skipped (missing required fields): {skipped}")

    return records, raw_rows


# =============================================================================
# Owner extraction
# =============================================================================

def extract_owners(records: list[dict], raw_rows: list[dict], cfg: dict) -> list[dict]:
    owner_fields = cfg.get('owner_fields', {})
    prefix       = cfg.get('owner_id_prefix') or cfg.get('collection_id', 'unknown')

    name_to_row = {
        r.get('owner_name'): raw
        for r, raw in zip(records, raw_rows)
        if r.get('owner_name')
    }

    seen: dict[str, dict] = {}
    for record in records:
        name = record.get('owner_name')
        if not name or name in seen:
            continue
        row = name_to_row.get(name, {})
        owner: dict = {
            'id':          f"{prefix}_{slugify(name)}",
            'title':       name,
            'description': None,
            'acronym':     None,
            'country':     None,
            'link':        None,
            'owner_type':  [],
        }
        for field, field_cfg in owner_fields.items():
            if not isinstance(field_cfg, dict):
                continue
            col = field_cfg.get('column', '')
            raw = row.get(col, '')
            if not raw or (isinstance(raw, float) and pd.isna(raw)):
                continue
            raw_str = str(raw).strip()
            if not raw_str:
                continue
            if field_cfg.get('type') == 'url':
                owner[field] = clean_url(raw_str)
            elif field_cfg.get('as_list'):
                owner[field] = [{'value': raw_str}]
            else:
                owner[field] = raw_str
        seen[name] = owner

    return list(seen.values())


# =============================================================================
# Main
# =============================================================================

def main() -> None:
    parser = argparse.ArgumentParser(
        description='Normalize a source file to IRD JSON.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument('source',   nargs='?', help='Input file (CSV, TSV, XLSX, JSON)')
    parser.add_argument('output',   nargs='?', help='Output innovations JSON')
    parser.add_argument('--config',           metavar='YAML',
                        help='Load column mapping from a config YAML/JSON file.')
    parser.add_argument('--save-config',      metavar='YAML',
                        help='Run interactive mapping and save config to this path.')
    parser.add_argument('--no-interactive',   action='store_true',
                        help='Skip mapping entirely — all columns go to _annotations.')
    parser.add_argument('--help-config',      action='store_true',
                        help='Print a config YAML template and exit.')
    parser.add_argument('--collection-id',    default='',
                        help='Collection machine name (e.g. stf).')
    parser.add_argument('--collection-label', default='',
                        help='Human-readable collection label.')
    parser.add_argument('--og-audience-nid',  type=int, default=0)
    parser.add_argument('--owner-nid',        type=int, default=0)
    parser.add_argument('--contact-nid',      type=int, default=0)
    args = parser.parse_args()

    if args.help_config:
        print(EXAMPLE_CONFIG)
        return

    if not args.source or not args.output:
        parser.error("source and output are required (unless --help-config)")

    # ── Load source ───────────────────────────────────────────────────────────
    source_path = Path(args.source)
    if not source_path.exists():
        sys.exit(f"Source not found: {source_path}")

    print(f"\nLoading {source_path}…")
    df = load_source(source_path)
    print(f"  {len(df)} rows × {len(df.columns)} columns")
    print(f"  Columns: {', '.join(df.columns[:8])}{'…' if len(df.columns) > 8 else ''}")

    # ── Determine config ──────────────────────────────────────────────────────
    if args.config:
        cfg = load_config(Path(args.config))
        print(f"  Config loaded from {args.config}")
    elif args.no_interactive:
        cfg = {
            'collection_id':    args.collection_id,
            'collection_label': args.collection_label,
            'owner_id_prefix':  args.collection_id,
            'required':         ['title'],
            'core':             {},
            'owner_fields':     {},
            'annotations':      {},
        }
    else:
        cfg = interactive_mapping(df, args)
        if args.save_config:
            save_config(cfg, Path(args.save_config))
        else:
            save_path = input(
                "\nSave config for reuse? Enter path (e.g. stf.yml) or Enter to skip: "
            ).strip()
            if save_path:
                save_config(cfg, Path(save_path))

    # ── Build records ─────────────────────────────────────────────────────────
    print("\nBuilding records…")
    records, raw_rows = build_records(df, cfg)
    owners            = extract_owners(records, raw_rows, cfg)

    collection_id    = cfg.get('collection_id')    or args.collection_id
    collection_label = cfg.get('collection_label') or args.collection_label

    # ── Write innovations JSON ────────────────────────────────────────────────
    out = {
        'meta': {
            'collection_id':    collection_id,
            'collection_label': collection_label,
            'og_audience_nid':  args.og_audience_nid,
            'owner_nid':        args.owner_nid,
            'contact_nid':      args.contact_nid,
            'record_count':     len(records),
            'export_timestamp': datetime.now().isoformat(),
        },
        'records': records,
    }
    out_path = Path(args.output)
    out_path.write_text(json.dumps(out, indent=2, ensure_ascii=False, default=str),
                        encoding='utf-8')
    print(f"\n✓ {len(records)} innovations → {out_path}")

    # ── Write owners JSON ─────────────────────────────────────────────────────
    owners_path = out_path.with_stem(out_path.stem + '_owners')
    owners_out  = {
        'meta': {
            'collection_id':    collection_id,
            'collection_label': collection_label,
            'record_count':     len(owners),
            'export_timestamp': datetime.now().isoformat(),
        },
        'records': owners,
    }
    owners_path.write_text(json.dumps(owners_out, indent=2, ensure_ascii=False, default=str),
                           encoding='utf-8')
    print(f"✓ {len(owners)} owners    → {owners_path}")

    # ── Coverage report ───────────────────────────────────────────────────────
    if records:
        first = records[0]
        all_core = list(CORE_FIELDS.keys())
        print("\nFirst record — core field coverage:")
        for f in all_core:
            val     = first.get(f)
            present = bool(val) if not isinstance(val, list) else len(val) > 0
            print(f"  {'✓' if present else '✗'} {f}")
        ann_keys = list(first.get('_annotations', {}).keys())
        print(f"\n  {len(ann_keys)} annotation keys: "
              f"{', '.join(ann_keys[:8])}{'…' if len(ann_keys) > 8 else ''}")


if __name__ == '__main__':
    main()
