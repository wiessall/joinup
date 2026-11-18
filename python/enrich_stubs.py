#!/usr/bin/env python3
"""Propose a website and a description for stub owners, with the logic of generate_org_descriptions.py.

  python3 enrich_stubs.py stubs.json proposals.json

stubs.json comes from `owner_api.py pull`, proposals.json goes to `owner_api.py push`. A rerun skips the owners
already in proposals.json, so an interrupted run resumes; a failed Gemini call leaves the owner out, to retry.
Owners that already have a proposal in Drupal (pending, reviewed or rejected) are skipped.

1. Candidate pages: the owner's own webpage, the URLs a grounded search names, and the sources it searched.
2. Each candidate, in order, is fetched (after redirects) and must mention the name or acronym. The page text
   then goes to the colleague's generate_from_scraped_text, which judges whether it is about this organisation
   or about a parent or another one. The first page judged "high" gives the webpage and the description.
   Where the page is hosted does not matter, only what it is about.
3. Only if no page passes: a grounded-search description, kept as `draft_description` (push ignores it),
   because nothing ties it to this organisation rather than a namesake.
"""

from __future__ import annotations

import datetime
import json
import re
import sys
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import requests

import generate_org_descriptions as G

MAX_CANDIDATES = 6     # pages fetched per owner
MIN_DESCRIPTION = 80   # as Completeness::MIN_DESCRIPTION in sti_owner_review: a shorter description is incomplete
MAX_JUDGED = 3         # pages sent to Gemini per owner
LEGAL_SUFFIX = re.compile(r"[\s,]+(llc|ltd\.?|inc\.?|gmbh|plc|s\.a\.?|pvt\.? ltd\.?|co\.? ltd\.?)$", re.I)


def context(stub: dict) -> str:
    """The colleague's context line, plus the innovations that reference the owner: often all we know about it."""
    ctx = G.build_org_context({G.COL_NAME: stub["title"], G.COL_ACRONYM: stub.get("acronym") or "",
                               G.COL_COUNTRY: stub.get("country") or "", G.COL_AGENCY_TYPE: ""})
    if stub.get("solutions"):
        ctx += " | Referenced by these innovations: " + "; ".join(stub["solutions"][:5])
    return ctx


def find_websites(ctx: str) -> tuple[list[str], str]:
    """URLs a grounded search names, then the search's own sources; and the model's raw answer, which also
    carries the PARENT line (see parent())."""
    prompt = (
        "Find the web page about this organization: its own website, or a page about it on another website, "
        "such as a parent organization's, a partner's or a project database. Not a news article or a social "
        "media page unless nothing else exists.\n\n"
        f"Organization context: {ctx}\n\n"
        "Reply with up to three lines, most likely first, each exactly 'WEBSITE: <url>' with a URL you found in "
        "your search. Reply 'WEBSITE: none' only if nothing about this organization can be found.\n"
        "Then one line 'PARENT: <full name>' if it is a programme, project, unit, office or subsidiary of another "
        "organization, or 'PARENT: none'.")
    response = G._generate_with_retry(model=G.MODEL_NAME, contents=prompt,
                                      config=G.GenerateContentConfig(tools=[G.Tool(google_search=G.GoogleSearch())]))
    raw = response.text or ""
    named = [u.rstrip(".,") for u in re.findall(r"WEBSITE:\W*(https?://[^\s<>\[\]()*\"']+)", raw)]
    try:
        chunks = response.candidates[0].grounding_metadata.grounding_chunks or []
        sources = [c.web.uri for c in chunks if getattr(c, "web", None) and c.web.uri]
    except (AttributeError, IndexError, TypeError):
        sources = []
    return named + sources, raw


def parent(raw: str) -> str | None:
    m = re.search(r"PARENT:\W*([^\n*]+)", raw)
    name = m.group(1).strip(" .*") if m else ""
    return None if not name or name.lower() == "none" else name


def fetch(url: str) -> tuple[str, str, object] | None:
    """(final URL after redirects, visible text, soup), trying the colleague's URL variants; None if nothing loads."""
    for candidate in G.url_variants(url):
        for verify in (True, False):
            try:
                resp = requests.get(candidate, timeout=G.REQUEST_TIMEOUT, headers=G.REQUEST_HEADERS, verify=verify)
                resp.raise_for_status()
            except requests.exceptions.SSLError:
                continue
            except requests.RequestException:
                break
            soup = G.BeautifulSoup(resp.text, "html.parser")
            for tag in soup(["script", "style", "nav", "footer"]):
                tag.decompose()
            return resp.url, re.sub(r"\s+", " ", soup.get_text(separator=" ")).strip(), soup
    return None


def mentions(text: str, stub: dict) -> bool:
    t = text.lower()
    names = {stub["title"].lower(), LEGAL_SUFFIX.sub("", stub["title"]).lower(), (stub.get("acronym") or "").lower()}
    return any(n and n in t for n in names)


def page_text(url: str, stub: dict) -> tuple[str, str] | None:
    """(final URL, homepage plus about-page text) if the page loads and mentions the organisation."""
    page = fetch(url)
    if not page:
        return None
    final, text, soup = page
    about = G.find_about_link(soup, final, stub["title"], stub.get("acronym") or "")
    if about and about != final and (more := fetch(about)):
        text += " " + more[1]
    text = text[:6000]
    return (final, text) if len(text) >= G.MIN_TEXT_LENGTH and mentions(text, stub) else None


def enrich(stub: dict) -> dict | None:
    name = stub["title"]
    ctx = context(stub)
    out = {"uuid": stub["uuid"], "title": name, "model": G.MODEL_NAME, "date": datetime.date.today().isoformat()}
    rejected = []
    try:
        candidates = [G.clean_url(stub.get("webpage") or "")]
        found, out["website_answer"] = find_websites(ctx)
        if p := parent(out["website_answer"]):
            out["parent"] = p
        candidates = list(dict.fromkeys(u for u in candidates + found if u))[:MAX_CANDIDATES]
        judged = 0
        for url in candidates:
            if judged == MAX_JUDGED:
                break
            page = page_text(url, stub)
            if not page:
                rejected.append({"url": url, "reason": "does not load or does not mention the organisation"})
                continue
            judged += 1
            result = G.generate_from_scraped_text(ctx, page[1], name)
            if result.confidence == "high":
                out.update(webpage=page[0], description=result.description, source_type="scraped",
                           evidence_url=page[0], confidence="high")
                break
            rejected.append({"url": page[0], "reason": result.reason or "low confidence"})
        if "webpage" not in out:
            result = G.generate_with_search_grounding(ctx, name)
            out.update(draft_description=result.description, source_type="search_grounded",
                       confidence=result.confidence, reason=result.reason)
    except Exception as e:
        G.log.warning(f"  Gemini call failed for {name}, will retry next run: {e}")
        return None
    out["rejected_websites"] = rejected
    return out


def complete(stub: dict) -> bool:
    """The same rule as Completeness::gaps() in sti_owner_review: a webpage and a description of MIN_DESCRIPTION+."""
    description = re.sub(r"<[^>]+>", "", stub.get("description") or "").strip()
    return bool(stub.get("webpage")) and len(description) >= MIN_DESCRIPTION


def main(src: Path, dst: Path) -> None:
    done = json.loads(dst.read_text())["records"] if dst.exists() else []
    seen = {r["uuid"] for r in done}
    todo = [s for s in json.loads(src.read_text())["records"]
            if s["uuid"] not in seen and not s.get("proposal_status") and not complete(s)]
    failed = 0
    for start in range(0, len(todo), G.BATCH_SIZE):
        with ThreadPoolExecutor(max_workers=G.MAX_CONCURRENT_WORKERS) as pool:
            batch = list(pool.map(enrich, todo[start:start + G.BATCH_SIZE]))
        done += [r for r in batch if r]
        failed += batch.count(None)
        dst.write_text(json.dumps({"records": done}, indent=1, ensure_ascii=False))
        G.log.info(f"saved {len(done)} proposals -> {dst}")
    G.log.info(f"{len(todo) - failed} enriched ({sum('webpage' in r for r in done)} with a website and a description "
               f"from it, {sum('draft_description' in r for r in done)} with a search-only draft), {failed} failed")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(Path(sys.argv[1]), Path(sys.argv[2]))
