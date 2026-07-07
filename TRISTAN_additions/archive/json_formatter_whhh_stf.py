#! /home/tristan/micromamba/envs/atio/bin/python3.13
"""
eca_agritech_normalize.py — ECA Agritech Observatory → IRD JSON schema
"""
import csv
import json
import argparse
from datetime import datetime
from pathlib import Path

# Maps standard IRD core fields to CSV columns.
# is_list=True → wrap values as [{"value": v}]
CORE_MAP = [
    ('title',               'Title',                   False),
    ('description',         'Description',             False),
    ('abstract',            'Short description',       False),
    ('url',                 'Link to the Observatory', False),
    ('date',                'Last update',             False),
    ('classification_name', 'Entry Type',              False),
    ('domain_names',        'Theme',                   True),
    ('topic_names',         'Use case',                True),
    ('owner_name',          'Organization',            False),
]
CORE_CSV_COLS = {col for _, col, _ in CORE_MAP}

LIST_COLS = {
    'Use case', 'Theme', 'Technology', 'Four betters',
    'SDGs', 'Countries and territories applied in', 'Food value chain stage',
}


def parse_list(value: str) -> list:
    if not value or not value.strip():
        return []
    try:
        result = json.loads(value)
        if not isinstance(result, list):
            result = [result]
        return [{"value": str(v)} for v in result if v]
    except (json.JSONDecodeError, ValueError):
        return [{"value": v.strip()} for v in value.split(',') if v.strip()]


def clean(value: str) -> str | None:
    v = value.strip() if value else ''
    return v or None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('source',  help='CSV file')
    parser.add_argument('output',  help='Output JSON file')
    parser.add_argument('--collection-id',    default='eca_agritech')
    parser.add_argument('--collection-label', default='ECA Agritech Observatory')
    parser.add_argument('--og-audience-nid',  type=int, default=0)
    parser.add_argument('--owner-nid',        type=int, default=0)
    parser.add_argument('--contact-nid',      type=int, default=0)
    args = parser.parse_args()

    records = []
    with open(args.source, newline='', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        reader.fieldnames = [k.strip().strip('"') for k in reader.fieldnames]
        all_cols = reader.fieldnames
        annotation_cols = [c for c in all_cols if c not in CORE_CSV_COLS]

        for row in reader:
            url   = clean(row.get('Link to the Observatory', ''))
            title = clean(row.get('Title', ''))
            if not title or not url:
                continue

            record = {
                'id':             url,   # observatory link is unique per record
                'language_names': [],    # not present in source
                'contact_name':   None,  # not present in source
            }

            for std_field, csv_col, is_list in CORE_MAP:
                raw = row.get(csv_col, '')
                record[std_field] = parse_list(raw) if is_list else clean(raw)

            # _annotations: all columns not mapped to core fields
            record['_annotations'] = {
                col: (parse_list(row[col]) if col in LIST_COLS else clean(row[col]))
                for col in annotation_cols
                if row.get(col, '').strip()
            }

            # _raw: original row as-is
            record['_raw'] = {k: v for k, v in row.items() if v and v.strip()}

            records.append(record)

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
    print(f"✓ {len(records)} records → {args.output}")

    if records:
        s = records[0]
        print("\nFirst record core coverage:")
        for f in ['id','title','description','abstract','url','date',
                  'classification_name','domain_names','topic_names','owner_name']:
            print(f"  {'✓' if s.get(f) else '✗'} {f}")
        print(f"  {len(s['_annotations'])} annotation keys")


if __name__ == '__main__':
    main()