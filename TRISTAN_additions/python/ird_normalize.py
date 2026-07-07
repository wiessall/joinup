#! /home/tristan/micromamba/envs/atio/bin/python3.13
"""
ird_normalize.py — IRD collection normalizer

Reads CSV/TSV/XLSX/JSON, maps source columns to the IRD core schema,
classifies remaining columns as claim keys (categorical or literal), and writes
the normalized JSON the migration + ird:annotate pipeline expects.

Output shape:
    {
      "meta": { "collection_id": "stf", "collection_nid": 42, … },
      "records": [
        {
          "id":    "stf_001",
          "title": "…",
          "claims": {
            "organization_type": { "value": "Non-profit",   "categorical": true  },
            "technologies":      { "value": ["GIS", "IoT"], "categorical": true  },
            "readiness_level":   { "value": "TRL 7",        "categorical": false }
          }
        }
      ]
    }

Classification rules (applied in order):
  1. Explicit categorical flag in claim config YAML → authoritative.
  2. Heuristic across all values for that key: average length ≤ 80 chars → categorical.

Modes:
  Batch (existing config):   ird_normalize.py source.csv out.json --config stf.yml
  Interactive (new source):  ird_normalize.py source.csv out.json --save-config stf.yml
  Headless (all → claims):  ird_normalize.py source.csv out.json --no-interactive
"""
from __future__ import annotations

import json
import re
import sys
from dataclasses import dataclass, field, asdict
from datetime import datetime
from pathlib import Path
from typing import Any

import pandas as pd

try:
    import yaml
    HAS_YAML = True
except ImportError:
    HAS_YAML = False


# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

AVG_FREE_TEXT_THRESHOLD = 80   # average value length above which a key is literal
MAX_TERM_NAME_LEN       = 240  # single value above which a key is always literal

CORE_FIELDS: dict[str, str] = {
    "id":                  "Record ID (unique, required)",
    "title":               "Title (required)",
    "description":         "Full description",
    "abstract":            "Short summary",
    "date":                "Date (YYYY-MM-DD preferred)",
    "url":                 "Landing page URL",
    # "classification_name": "Classification / innovation type",
    # "domain_names":        "Domain(s) [multi-value]",
    # "topic_names":         "Topic(s) [multi-value]",
    "language_names":      "Language(s) [multi-value]",
    "owner_name":          "Owner organisation name",
    "contact_name":        "Contact person name",
}

MULTI_VALUE_CORE = {"domain_names", "topic_names", "language_names"}
CORE_DEFAULTS: dict[str, Any] = {"language_names": [], "contact_name": None}


# ---------------------------------------------------------------------------
# Config dataclasses
# ---------------------------------------------------------------------------

#! Dataclass update: No more separate owners; everything goes into claims
#! One metadata config class containing syntaxt and categorical 
@dataclass
class FieldConfig:
    column:       str
    type:         str | None  = None   # "url"
    categorical:  bool | None = None   # None = auto-detect via heuristic
    separator:    str | None  = None
    strip_prefix: str | None  = None
    group_as:     str | None  = None   # merge into this claim key


# @dataclass
# class ClaimConfig:
#     separator:    str | None  = None
#     strip_prefix: str | None  = None


# @dataclass
# class OwnerFieldConfig:
#     column:   str
#     type:     str | None  = None   # "url"
#     as_list:  bool        = False


@dataclass
class CollectionConfig:
    collection_id:    str
    collection_label: str
    owner_id_prefix:  str                         = ""
    required:         list[str]                   = field(default_factory=lambda: ["title"])
    core:             dict[str, FieldConfig]      = field(default_factory=dict)
    claims:           dict[str, FieldConfig]      = field(default_factory=dict) #!

    def core_source_columns(self) -> set[str]:
        return {cfg.column for cfg in self.core.values()}


# ---------------------------------------------------------------------------
# Config I/O
# ---------------------------------------------------------------------------

def load_config(path: Path) -> CollectionConfig:
    if not HAS_YAML:
        sys.exit("pyyaml required for --config.  pip install pyyaml")
    with open(path, encoding="utf-8") as f:
        raw = yaml.safe_load(f)

    def _load_field_configs(section: str) -> dict[str, FieldConfig]:
        return {
            k: FieldConfig(**{fk: fv for fk, fv in v.items() if fk in FieldConfig.__dataclass_fields__})
            for k, v in raw.get(section, {}).items()
            if v and isinstance(v, dict) and "column" in v
        }

    return CollectionConfig(
        collection_id    = raw.get("collection_id", ""),
        collection_label = raw.get("collection_label", ""),
        owner_id_prefix  = raw.get("owner_id_prefix", raw.get("collection_id", "")),
        required         = raw.get("required", ["title"]),
        core             = _load_field_configs("core"),
        claims           = _load_field_configs("claims"),
    )

def save_config(cfg: CollectionConfig, path: Path) -> None:
    # Convert dataclasses to plain dicts, dropping None values.
    def _clean(d: dict) -> dict:
        return {k: v for k, v in d.items() if v is not None and v != [] and v != {}}

    raw = {
        "collection_id":    cfg.collection_id,
        "collection_label": cfg.collection_label,
        "owner_id_prefix":  cfg.owner_id_prefix,
        "required":         cfg.required,
        "core":             {k: _clean(asdict(v)) for k, v in cfg.core.items()},
        "claims":           {k: _clean(asdict(v)) for k, v in cfg.claims.items()},
    }
    if HAS_YAML:
        with open(path, "w", encoding="utf-8") as f:
            yaml.dump(raw, f, allow_unicode=True, sort_keys=False)
    else:
        path.with_suffix(".json").write_text(json.dumps(raw, indent=2, ensure_ascii=False))
    print(f"  Config saved → {path}")


# ---------------------------------------------------------------------------
# Source loading
# ---------------------------------------------------------------------------

def load_source(path: Path) -> pd.DataFrame:
    loaders = {
        ".csv":  lambda p: pd.read_csv(p, dtype=str, encoding="utf-8-sig"),
        ".tsv":  lambda p: pd.read_table(p, dtype=str),
        ".txt":  lambda p: pd.read_table(p, dtype=str),
        ".xlsx": lambda p: pd.read_excel(p, dtype=str),
        ".xls":  lambda p: pd.read_excel(p, dtype=str),
        ".json": lambda p: pd.read_json(p),
    }
    loader = loaders.get(path.suffix.lower())
    if not loader:
        sys.exit(f"Unsupported format: {path.suffix}")
    df = loader(path).dropna(how="all")
    df.columns = [_normalize_header(str(c)) for c in df.columns]
    return df


# ---------------------------------------------------------------------------
# Value utilities
# ---------------------------------------------------------------------------

def _normalize_header(h: str) -> str:
    """Strip BOM, whitespace, quotes; keep only the first line of multi-line headers."""
    return h.strip().strip("\"'").lstrip("\ufeff").splitlines()[0].strip()


def slugify(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    try:
        if pd.isna(value):
            return None
    except (TypeError, ValueError):
        pass
    v = str(value).strip()
    return v or None


def _clean_url(value: Any) -> str | None:
    v = _clean(value)
    if not v:
        return None
    v = v.split()[0]
    if not v.startswith(("http://", "https://")):
        v = "https://" + v
    return v if "." in v.split("://", 1)[-1] else None


def _parse_list(raw: Any, sep: str, strip_prefix: str | None = None) -> list[str]:
    """Split a delimited field into a flat list of strings."""
    if _clean(raw) is None:
        return []
    parts = [p.strip() for p in str(raw).split(sep) if p.strip()]
    if strip_prefix:
        parts = [re.sub(strip_prefix, "", p).strip() for p in parts]
    return [p for p in parts if p]


def _detect_separator(samples: list[str]) -> str | None:
    non_empty = [s for s in samples if s and str(s).strip()]
    if len(non_empty) < 3:
        return None
    for sep in ("|", ";"):
        if sum(1 for s in non_empty if sep in str(s)) / len(non_empty) >= 0.5:
            return sep
    return None


# ---------------------------------------------------------------------------
# Claim classification
# ---------------------------------------------------------------------------

class ClaimClassifier:
    """
    Determines whether each claim key is categorical or literal.

    Classification order:
      1. Explicit flag in ClaimConfig.categorical → authoritative.
      2. Heuristic: average value length and maximum value length.
    """

    def __init__(self, records: list[dict], field_configs: dict[str, FieldConfig]) -> None:
        self._configs = field_configs
        self._results: dict[str, bool] = {}
        self._analyse(records)

    def _analyse(self, records: list[dict]) -> None:
        values_by_key: dict[str, list[str]] = {}
        for record in records:
            for key, claim in record.get("_raw_claims", {}).items():
                resolved_key = self._configs.get(key, FieldConfig(column=key)).group_as or key
                values_by_key.setdefault(resolved_key, [])
                if isinstance(claim, list):
                    values_by_key[resolved_key].extend(str(i) for i in claim)
                else:
                    values_by_key[resolved_key].append(str(claim))

        for key, values in values_by_key.items():
            explicit = self._configs.get(key, FieldConfig(column=key)).categorical
            if explicit is not None:
                self._results[key] = explicit
            else:
                self._results[key] = self._heuristic(values)

    @staticmethod
    def _heuristic(values: list[str]) -> bool:
        if not values:
            return False
        if any(len(v) > MAX_TERM_NAME_LEN for v in values):
            return False
        return sum(len(v) for v in values) / len(values) <= AVG_FREE_TEXT_THRESHOLD

    def is_categorical(self, key: str) -> bool:
        return self._results.get(key, False)


# ---------------------------------------------------------------------------
# Record mapping
# ---------------------------------------------------------------------------

class RecordMapper:
    """Maps source DataFrame rows to IRD records using a CollectionConfig."""

    def __init__(self, cfg: CollectionConfig) -> None:
        self.cfg = cfg

    def map_all(self, df: pd.DataFrame) -> list[dict]:
        """First pass: build raw records (without categorical flags)."""
        records = []
        skipped = 0
        claim_cols = [c for c in df.columns if c not in self.cfg.core_source_columns()]

        for _, row in df.iterrows():
            record = self._map_core(row)
            if any(not record.get(f) for f in self.cfg.required):
                skipped += 1
                continue
            if record.get("owner_name"):
                record["owner_id"] = f"{self.cfg.owner_id_prefix}_{slugify(record['owner_name'])}"
            record["_raw_claims"] = self._map_raw_claims(row, claim_cols)
            records.append(record)

        if skipped:
            print(f"  Skipped {skipped} rows (missing required fields).")
        return records

    def apply_classification(self, records: list[dict], classifier: ClaimClassifier) -> list[dict]:
        """Second pass: convert raw claims to {value, categorical} shape."""
        for record in records:
            claims = {}
            for key, value in record.pop("_raw_claims", {}).items():
                resolved_key = self.cfg.claims.get(key, FieldConfig(column=key)).group_as or key
                is_cat = classifier.is_categorical(resolved_key)
                if resolved_key in claims and isinstance(claims[resolved_key]["value"], list):
                    # Merge grouped columns.
                    extra = value if isinstance(value, list) else [value]
                    claims[resolved_key]["value"].extend(extra)
                else:
                    claims[resolved_key] = {"value": value}
            record["claims"] = claims
        return records

    def _map_core(self, row: pd.Series) -> dict:
        record: dict = dict(CORE_DEFAULTS)
        for std_field, cfg in self.cfg.core.items():
            raw = row.get(cfg.column, "")
            if cfg.type == "url":
                record[std_field] = _clean_url(raw)
            elif cfg.separator:
                record[std_field] = _parse_list(raw, cfg.separator, cfg.strip_prefix)
            else:
                record[std_field] = _clean(raw)
        return record

    def _map_raw_claims(self, row: pd.Series, claim_cols: list[str]) -> dict:
        raw_claims: dict = {}
        for col in claim_cols:
            raw = row.get(col)
            v = _clean(raw)
            if not v:
                continue
            cfg = self.cfg.claims.get(col, FieldConfig(column=col))
            value: str | list[str] = _parse_list(v, cfg.separator, cfg.strip_prefix) if cfg.separator else v
            raw_claims[col] = value
        return raw_claims


# ---------------------------------------------------------------------------
# Interactive configurator
# ---------------------------------------------------------------------------

class InteractiveConfigurator:
    """Walks the user through mapping source columns to the IRD schema."""

    def __init__(self, df: pd.DataFrame, collection_id: str = "", collection_label: str = "") -> None:
        self.df = df
        self.cols = list(df.columns)
        self._collection_id    = collection_id
        self._collection_label = collection_label

    def run(self) -> CollectionConfig:
        self._print_columns()
        collection_id    = self._collection_id    or input("\nCollection ID (machine name, e.g. 'stf'): ").strip()
        collection_label = self._collection_label or input("Collection label: ").strip()

        print("\n── Core field mapping (Enter to skip) " + "─" * 30)
        core, mapped_cols = self._map_core_fields()

        print("\n── Claim columns " + "─" * 50)
        claim_cols = [c for c in self.cols if c not in mapped_cols]
        claims = self._map_claim_columns(claim_cols)

        return CollectionConfig(
            collection_id    = collection_id,
            collection_label = collection_label,
            owner_id_prefix  = collection_id,
            core             = core,
            claims           = claims,
        )

    def _print_columns(self) -> None:
        print("\n── Source columns " + "─" * 50)
        for i, col in enumerate(self.cols, start=1):
            sample = self.df[col].dropna().iloc[0] if not self.df[col].dropna().empty else ""
            print(f"  [{i:>3}] {col:<40}  e.g. {str(sample)[:60].replace(chr(10), ' ')}")

    def _pick_column(self, prompt: str, allow_empty: bool = True) -> str | None:
        while True:
            raw = input(f"\n  {prompt}: ").strip()
            if not raw:
                return None if allow_empty else self._pick_column(prompt, allow_empty)
            if raw.isdigit():
                idx = int(raw) - 1  # 1-based
                if 0 <= idx < len(self.cols):
                    return self.cols[idx]
            if raw in self.cols:
                return raw
            print(f"    ✗ '{raw}' not recognised — enter an index (1-based) or exact column name.")

    def _ask_separator(self, col: str) -> tuple[str | None, str | None]:
        samples = self.df[col].dropna().tolist()[:20]
        suggested = _detect_separator([str(s) for s in samples])
        prompt = f"    Separator for '{col}'"
        prompt += f" (detected '{suggested}', Enter to accept)" if suggested else " (Enter to skip)"
        raw = input(prompt + ": ").strip()
        sep = raw if raw else suggested
        strip = None
        if sep:
            strip = input("    Strip-prefix regex (Enter to skip): ").strip() or None
        return sep, strip

    def _map_core_fields(self) -> tuple[dict[str, FieldConfig], set[str]]:
        core: dict[str, FieldConfig] = {}
        mapped: set[str] = set()
        for field_name, description in CORE_FIELDS.items():
            col = self._pick_column(f"{field_name}  ({description})")
            if not col:
                continue
            mapped.add(col)
            cfg = FieldConfig(column=col)
            if field_name == "url":
                cfg.type = "url"
            elif field_name in MULTI_VALUE_CORE:
                cfg.separator, cfg.strip_prefix = self._ask_separator(col)
            core[field_name] = cfg
        return core, mapped

    def _map_claim_columns(self, claim_cols: list[str]) -> dict[str, FieldConfig]:
        claims: dict[str, FieldConfig] = {}
        print(f"{len(claim_cols)} columns remain → claims. Configure per-column (Enter to accept defaults).\n")
        for col in claim_cols:
            raw_cat = input(f"  '{col}' — categorical? [y/N]: ").strip().lower()
            categorical: bool | None = (
                True if raw_cat in ("y", "yes") else 
                False if raw_cat in ("n", "no") else
                None
            )
            sep, strip = self._ask_separator(col)
            group = input(f"    Group-as key (merge with another column, Enter to skip): ").strip() or None
            claims[col] = FieldConfig(column=col, separator=sep, strip_prefix=strip, categorical=categorical, group_as=group)
        return claims

    def _map_owner_fields(self) -> dict[str, OwnerFieldConfig]:
        owner_fields: dict[str, OwnerFieldConfig] = {}
        for field_name, hint in [
            ("country",     "Country of the org"),
            ("link",        "Website URL"),
            ("owner_type",  "Org type (stored as list)"),
            ("description", "Org description"),
            ("acronym",     "Acronym"),
        ]:
            col = self._pick_column(f"owner.{field_name}  ({hint})")
            if col:
                cfg = OwnerFieldConfig(column=col)
                if field_name == "link":
                    cfg.type = "url"
                elif field_name == "owner_type":
                    cfg.as_list = True
                owner_fields[field_name] = cfg
        return owner_fields


# ---------------------------------------------------------------------------
# Normalizer — top-level pipeline
# ---------------------------------------------------------------------------

class Normalizer:
    def __init__(self, cfg: CollectionConfig, collection_nid: int = 0) -> None:
        self.cfg            = cfg
        self.collection_nid = collection_nid

    def run(self, df: pd.DataFrame, out_path: Path) -> None:
        mapper       = RecordMapper(self.cfg)
        records      = mapper.map_all(df)
        classifier   = ClaimClassifier(records, self.cfg.claims)
        records      = mapper.apply_classification(records, classifier)
        field_config = self._build_field_config(records, classifier)
        self._write_innovations(records, field_config, out_path)
        self._print_summary(records, field_config)

    def _build_field_config(self, records: list[dict], classifier: ClaimClassifier) -> dict:
        all_keys: set[str] = set()
        for r in records:
            all_keys.update(r.get("claims", {}).keys())
        result: dict[str, dict] = {}
        for key in sorted(all_keys):
            fm    = self.cfg.claims.get(key, FieldConfig(column=key))
            entry = {"categorical": classifier.is_categorical(key)}
            if fm.separator:    entry["separator"]    = fm.separator
            if fm.strip_prefix: entry["strip_prefix"] = fm.strip_prefix
            if fm.type:         entry["type"]         = fm.type
            result[key] = entry
        return result

    def _write_innovations(self, records: list[dict], field_config: dict, path: Path) -> None:
        # --- field_config: one entry per claim key, lives inside meta ---
        all_keys: set[str] = set()
        for r in records:
            all_keys.update(r.get("claims", {}).keys())

        # --- records: core_fields nested, claims as {value: ...} without categorical ---
        clean_records = []
        for r in records:
            clean_records.append({
                "core_fields": {k: v for k, v in r.items() if k != "claims"},
                "claims":      {k: {"value": v["value"]} for k, v in r.get("claims", {}).items()},
            })

        out = {
            "meta": {
                "collection_id":    self.cfg.collection_id,
                "collection_label": self.cfg.collection_label,
                "collection_nid":   self.collection_nid,
                "record_count":     len(clean_records),
                "export_timestamp": datetime.now().isoformat(),
                "field_config":     field_config,
            },
            "records": clean_records,
        }
        path.write_text(json.dumps(out, indent=2, ensure_ascii=False, default=str), encoding="utf-8")
        print(f"\n✓ {len(clean_records)} records → {path}")

    def _print_summary(self, records: list[dict], field_config: dict) -> None:
        if not records:
            return
        first = records[0]
        print("\nFirst record — core fields:")
        for f in CORE_FIELDS:
            v = first.get("core_fields", {}).get(f)
            print(f"  {'✓' if v else '✗'} {f}")
        keys = list(first.get("claims", {}).keys())
        cats = sum(1 for k in keys if field_config.get(k, {}).get("categorical", False))
        print(f"\n  {len(keys)} claim keys  ({cats} categorical, {len(keys) - cats} literal)")
        print(f"  Keys: {', '.join(keys[:10])}{'…' if len(keys) > 10 else ''}")

# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main() -> None:
    import argparse
    parser = argparse.ArgumentParser(
        description="Normalize a source file to IRD JSON.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("source",              nargs="?")
    parser.add_argument("output",              nargs="?")
    parser.add_argument("--config",            metavar="YAML")
    parser.add_argument("--save-config",       metavar="YAML")
    parser.add_argument("--no-interactive",    action="store_true")
    parser.add_argument("--collection-id",     default="")
    parser.add_argument("--collection-label",  default="")
    parser.add_argument("--collection-nid",    type=int, default=0)
    args = parser.parse_args()

    if not args.source or not args.output:
        parser.error("source and output are required")

    source_path = Path(args.source)
    if not source_path.exists():
        sys.exit(f"Source not found: {source_path}")

    print(f"\nLoading {source_path}…")
    df = load_source(source_path)
    print(f"  {len(df)} rows × {len(df.columns)} columns")

    if args.config:
        cfg = load_config(Path(args.config))
        print(f"  Config: {args.config}")
    elif args.no_interactive:
        cfg = CollectionConfig(
            collection_id    = args.collection_id,
            collection_label = args.collection_label,
            owner_id_prefix  = args.collection_id,
        )
    else:
        configurator = InteractiveConfigurator(df, args.collection_id, args.collection_label)
        cfg = configurator.run()
        save_path = Path(args.save_config) if args.save_config else None
        if not save_path:
            raw = input("\nSave config? Enter path (e.g. stf.yml) or Enter to skip: ").strip()
            save_path = Path(raw) if raw else None
        if save_path:
            save_config(cfg, save_path)

    Normalizer(cfg, collection_nid=args.collection_nid).run(df, Path(args.output))


if __name__ == "__main__":
    main()