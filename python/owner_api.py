#!/usr/bin/env python3
"""Read stub owners from Drupal for enrichment, and write enrichment results back.

  python3 owner_api.py pull https://sti.ddev.site stubs.json
      every owner in needs_update or marked "Incomplete - to be enriched", with the titles of the solutions that
      reference it (search context)

  python3 owner_api.py push https://sti.ddev.site proposals.json
      proposals.json from enrich_stubs.py. Each record becomes a pending proposal in the owner's
      field_ai_proposal; the owner's own fields are not touched. A curator accepts or rejects it field by field
      at /admin/content/owner-review (sti_owner_review). An owner whose proposal was already reviewed or
      rejected is skipped, so a decision is never overwritten.

Credentials (basic_auth) of a user who may view unpublished owners and has "submit owner proposals":
STI_USER and STI_PASS.
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import requests

STATE = "needs_update"
# Record status sti_owner_review derives on every save when a required field is empty.
INCOMPLETE = "Incomplete - to be enriched"


def owner_filter() -> dict:
    """Owners to enrich: in needs_update (stubs from imports) OR marked incomplete (published, missing information)."""
    return {
        "filter[q][group][conjunction]": "OR",
        "filter[s][condition][path]": "field_state",
        "filter[s][condition][value]": STATE,
        "filter[s][condition][memberOf]": "q",
        "filter[i][condition][path]": "field_record_status.name",
        "filter[i][condition][value]": INCOMPLETE,
        "filter[i][condition][memberOf]": "q",
    }


def session(base: str) -> requests.Session:
    s = requests.Session()
    s.auth = (os.environ["STI_USER"], os.environ["STI_PASS"])
    s.headers.update({"Accept": "application/vnd.api+json", "Content-Type": "application/vnd.api+json"})
    # Without basic_auth Drupal ignores the credentials and answers as anonymous, which hides unpublished stubs.
    r = s.get(f"{base}/jsonapi", timeout=60)
    r.raise_for_status()
    if "me" not in r.json().get("meta", {}).get("links", {}):
        sys.exit("Not authenticated: is basic_auth installed (ddev drush pm:install basic_auth) "
                 "and are STI_USER/STI_PASS right?")
    return s


def pages(s: requests.Session, url: str, params: dict, included: dict | None = None):
    """All items of a JSON:API collection, following the next links; included resources go into `included`."""
    while url:
        r = s.get(url, params=params, timeout=60)
        r.raise_for_status()
        body = r.json()
        if "omitted" in body.get("meta", {}):
            print(f"WARNING {r.url}: some results were left out for lack of access: "
                  f"{body['meta']['omitted'].get('detail', '')}", file=sys.stderr)
        if included is not None:
            included.update({i["id"]: i["attributes"] for i in body.get("included", [])})
        yield from body["data"]
        url, params = body.get("links", {}).get("next", {}).get("href"), None


def related(item: dict, field: str) -> list[str]:
    """Ids a relationship points to; empty if the field is missing or empty."""
    data = item.get("relationships", {}).get(field, {}).get("data")
    return [d["id"] for d in (data if isinstance(data, list) else [data]) if d]


def pull(base: str, out: Path) -> None:
    s = session(base)
    owners, terms = {}, {}
    for o in pages(s, f"{base}/jsonapi/node/owner", owner_filter() | {"include": "field_country_origin"}, terms):
        a = o["attributes"]
        owners[o["id"]] = {
            "uuid": o["id"],
            "nid": a["drupal_internal__nid"],
            "title": a["title"],
            "acronym": a.get("field_acronym"),
            "country": "; ".join(terms[t]["name"] for t in related(o, "field_country_origin") if t in terms),
            "webpage": (a.get("webpage") or {}).get("uri"),
            "description": (a.get("description") or {}).get("value"),
            "proposal_status": proposal_status(a.get("field_ai_proposal")),
            "solutions": [],
        }
    uuids = list(owners)
    for start in range(0, len(uuids), 50):
        for sol in pages(s, f"{base}/jsonapi/node/solution", {
            "filter[o][condition][path]": "owner.id",
            "filter[o][condition][operator]": "IN",
            "filter[o][condition][value][]": uuids[start:start + 50],
            "fields[node--solution]": "title,owner",
        }):
            for ref in related(sol, "owner"):
                if ref in owners:
                    owners[ref]["solutions"].append(sol["attributes"]["title"])
    out.write_text(json.dumps({"records": list(owners.values())}, indent=1, ensure_ascii=False))
    print(f"{len(owners)} owners in {STATE} or marked '{INCOMPLETE}' -> {out}", file=sys.stderr)


def proposal_status(raw: str | None) -> str | None:
    try:
        return json.loads(raw).get("status") if raw else None
    except (ValueError, AttributeError):
        return "unreadable"


PROVENANCE = ("source_type", "evidence_url", "model", "date", "confidence", "reason", "rejected_websites")


def build_proposal(current_raw: str | None, record: dict) -> str | None:
    """The field_ai_proposal value to write, or None: nothing to propose, or a curator already decided."""
    if proposal_status(current_raw) not in (None, "pending"):
        return None
    fields = {k: record[k] for k in ("webpage", "parent") if record.get(k)}
    # A search-only description is proposed too; the review form flags it and leaves it unticked.
    description = record.get("description") or record.get("draft_description")
    if description:
        fields["description"] = description
    if not fields:
        return None
    return json.dumps({"status": "pending", "fields": fields} | {k: record[k] for k in PROVENANCE if k in record},
                      ensure_ascii=False)


def push(base: str, proposals: Path) -> None:
    s = session(base)
    written = skipped = failed = 0
    for p in json.loads(proposals.read_text())["records"]:
        url = f"{base}/jsonapi/node/owner/{p['uuid']}"
        # Read just before writing, so a decision taken since the pull is never overwritten.
        r = s.get(url, timeout=60)
        r.raise_for_status()
        value = build_proposal(r.json()["data"]["attributes"].get("field_ai_proposal"), p)
        if value is None:
            skipped += 1
            continue
        r = s.patch(url, timeout=60, data=json.dumps(
            {"data": {"type": "node--owner", "id": p["uuid"], "attributes": {"field_ai_proposal": value}}}))
        r.raise_for_status()
        if r.json()["data"]["attributes"].get("field_ai_proposal") != value:
            failed += 1
            print(f"ERROR {p['uuid']}: PATCH succeeded but the proposal did not save (does the account have "
                  f"'submit owner proposals'?)", file=sys.stderr)
        else:
            written += 1
    print(f"{written} proposals written, {skipped} skipped (nothing to propose, or already decided), {failed} failed",
          file=sys.stderr)
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    if len(sys.argv) != 4 or sys.argv[1] not in ("pull", "push"):
        sys.exit(__doc__)
    {"pull": pull, "push": push}[sys.argv[1]](sys.argv[2].rstrip("/"), Path(sys.argv[3]))
