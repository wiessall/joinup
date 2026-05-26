#! /home/tristan/micromamba/envs/atio/bin/python3.13
"""
ird_normalize.py — IRD lib collection normalizer

Reads an arbitrary source file (CSV, TSV, Excel), lets the user map
source columns to core schema fields, assigns the rest to _annotations,
and writes the normalized IRD JSON.

Usage:
    python ird_normalize.py <source_file> <output_file> [--collection-id ID]

Example:
    python ird_normalize.py dah.csv dah.json --collection-id dah
"""

import json
import argparse
import sys
from pathlib import Path

import pandas as pd

# =============================================================================
# Core schema fields the migration expects.
# Everything in the source that doesn't map here goes to _annotations.
# =============================================================================
CORE_FIELDS = {
    "id":                  "Record ID (unique, required)",
    "title":               "Title (required)",
    "description":         "Full description (HTML ok)",
    "abstract":            "Short summary (255 chars max)",
    "date":                "Date (YYYY-MM-DD)",
    "url":                 "Landing page URL",
    "classification_name": "Classification / innovation type",
    "domain_names":        "Domain(s) — comma-separated or array",
    "topic_names":         "Topic(s) — comma-separated or array",
    "language_names":      "Language(s) — comma-separated or array",
    "owner_name":          "Owner organisation name",
    "contact_name":        "Contact person name",
}

ARRAY_FIELDS = {"domain_names", "topic_names", "language_names"}


def load_source(path: Path) -> pd.DataFrame:
    suffix = path.suffix.lower()
    if suffix == ".csv":
        return pd.read_csv(path)
    elif suffix in (".tsv", ".txt"):
        return pd.read_table(path)
    elif suffix in (".xlsx", ".xls"):
        return pd.read_excel(path)
    elif suffix == ".json":
        return pd.read_json(path)
    else:
        raise ValueError(f"Unsupported source format: {suffix}")


def prompt_mapping(source_cols: list[str]) -> dict[str, str | None]:
    """
    Interactive CLI: for each core field, let the user pick a source column.
    Returns {core_field: source_col} — value is None if skipped.
    """
    print("\n── Column mapping ──────────────────────────────────────────")
    print("Source columns:")
    for i, col in enumerate(source_cols):
        print(f"  [{i}] {col}")
    print()
    print("For each core field, enter the column number, column name,")
    print("or press Enter to skip.\n")

    mapping = {}
    for field, description in CORE_FIELDS.items():
        while True:
            raw = input(f"  {field} ({description}): ").strip()
            if raw == "":
                mapping[field] = None
                break
            # Accept index or name
            if raw.isdigit() and int(raw) < len(source_cols):
                mapping[field] = source_cols[int(raw)]
                break
            elif raw in source_cols:
                mapping[field] = raw
                break
            else:
                print(f"    ✗ '{raw}' not found. Try again or press Enter to skip.")

    return mapping


def coerce_array(value) -> list[str]:
    """Normalise a value to a list of strings."""
    if pd.isna(value) if not isinstance(value, (list, dict)) else False:
        return []
    if isinstance(value, list):
        return [str(v) for v in value if v is not None]
    if isinstance(value, str):
        # Split on comma or semicolon if it looks like a list
        if "," in value:
            return [v.strip() for v in value.split(",") if v.strip()]
        if ";" in value:
            return [v.strip() for v in value.split(";") if v.strip()]
        return [value] if value.strip() else []
    return [str(value)]


def coerce_value(value):
    """Clean a scalar value — return None for NaN/empty."""
    if value is None:
        return None
    try:
        if pd.isna(value):
            return None
    except (TypeError, ValueError):
        pass
    v = str(value).strip()
    return v if v else None


def build_records(df: pd.DataFrame, mapping: dict[str, str | None]) -> list[dict]:
    # Which source columns are mapped to core fields
    mapped_source_cols = {v for v in mapping.values() if v is not None}
    annotation_cols = [c for c in df.columns if c not in mapped_source_cols]

    records = []
    for _, row in df.iterrows():
        record = {}

        # Core fields
        for field, source_col in mapping.items():
            if source_col is None:
                record[field] = [] if field in ARRAY_FIELDS else None
                continue
            value = row[source_col]
            if field in ARRAY_FIELDS:
                record[field] = coerce_array(value)
            else:
                record[field] = coerce_value(value)

        # _raw: original row as dict
        record["_raw"] = {
            col: (None if pd.isna(v) else v)
            for col, v in row.items()
            if not isinstance(v, float) or not pd.isna(v)
        }

        # _annotations: all unmapped columns
        record["_annotations"] = {}
        for col in annotation_cols:
            val = row[col]
            if isinstance(val, list):
                record["_annotations"][col] = val
            elif pd.isna(val) if not isinstance(val, list) else False:
                record["_annotations"][col] = None
            else:
                record["_annotations"][col] = val

        records.append(record)

    return records


def main():
    parser = argparse.ArgumentParser(description="Normalize a source file to IRD JSON format.")
    parser.add_argument("source",      help="Source file (CSV, TSV, XLSX, JSON)")
    parser.add_argument("output",      help="Output JSON file")
    parser.add_argument("--collection-id",    default="",   help="Collection machine name (e.g. 'car')")
    parser.add_argument("--collection-label", default="",   help="Human-readable collection label")
    parser.add_argument("--og-audience-nid",  type=int, default=0)
    parser.add_argument("--owner-nid",        type=int, default=0)
    parser.add_argument("--contact-nid",      type=int, default=0)
    parser.add_argument("--no-interactive",   action="store_true",
                        help="Skip interactive mapping (all columns go to _annotations)")
    args = parser.parse_args()

    source_path = Path(args.source)
    if not source_path.exists():
        print(f"Error: {source_path} not found.", file=sys.stderr)
        sys.exit(1)

    print(f"Loading {source_path}...")
    df = load_source(source_path)
    print(f"  {len(df)} rows, {len(df.columns)} columns")

    if args.no_interactive:
        mapping = {field: None for field in CORE_FIELDS}
    else:
        mapping = prompt_mapping(list(df.columns))

    print("\nBuilding records...")
    records = build_records(df, mapping)

    output = {
        "meta": {
            "collection_id":     args.collection_id,
            "collection_label":  args.collection_label,
            "og_audience_nid":   args.og_audience_nid,
            "owner_nid":         args.owner_nid,
            "contact_nid":       args.contact_nid,
            "record_count":      len(records),
            "export_timestamp":  pd.Timestamp.now().isoformat(),
        },
        "records": records,
    }

    output_path = Path(args.output)
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(output, f, ensure_ascii=False, indent=2, default=str)

    print(f"\n✓ Written {len(records)} records to {output_path}")

    # Summary of mapping
    print("\n── Mapping summary ─────────────────────────────────────────")
    for field, source_col in mapping.items():
        status = f"← {source_col}" if source_col else "  (skipped)"
        print(f"  {field:25s} {status}")

    unmapped = [c for c in df.columns if c not in {v for v in mapping.values() if v}]
    print(f"\n  {len(unmapped)} columns → _annotations: {', '.join(unmapped[:8])}")
    if len(unmapped) > 8:
        print(f"    ... and {len(unmapped) - 8} more")


if __name__ == "__main__":
    main()