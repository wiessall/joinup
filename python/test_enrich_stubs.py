"""Silent failures: a wrong website, or a description of a parent or a namesake, proposed to Drupal; a failed call
remembered as an answer."""

import types

import enrich_stubs as E

STUB = {"uuid": "u1", "title": "Soil Doctors", "acronym": "", "solutions": ["Farmer-to-farmer soil training"]}
D = E.G.OrgDescription


def fake(monkeypatch, candidates, pages, judge=lambda text: D(description=text[:20], confidence="high"),
         grounded=lambda: D(description="From search.", confidence="high")):
    """candidates: URLs search finds; pages: url -> (final url, text); judge/grounded: the Gemini answers."""
    monkeypatch.setattr(E, "find_websites", lambda ctx: (candidates, "raw"))
    monkeypatch.setattr(E, "fetch", lambda url: (pages[url][0], pages[url][1], None) if url in pages else None)
    monkeypatch.setattr(E.G, "find_about_link", lambda *a: None)
    monkeypatch.setattr(E.G, "generate_from_scraped_text", lambda ctx, text, name: judge(text))
    monkeypatch.setattr(E.G, "generate_with_search_grounding", lambda ctx, name: grounded())


def test_page_on_parent_site_about_the_org_is_accepted(monkeypatch):
    url = "https://www.fao.org/global-soil-partnership/soil-doctors"
    fake(monkeypatch, [url], {url: (url, "The Soil Doctors programme trains farmers " * 10)})
    out = E.enrich(STUB)
    assert out["webpage"] == url and out["source_type"] == "scraped"


def test_page_about_the_parent_is_rejected_and_next_candidate_tried(monkeypatch):
    parent, own = "https://fao.org", "https://soildoctors.org"
    fake(monkeypatch, [parent, own], {parent: (parent, "FAO ... Soil Doctors " * 10), own: (own, "Soil Doctors " * 30)},
         judge=lambda t: D(description="x", confidence="low", reason="Parent organization content")
         if t.startswith("FAO") else D(description="Own.", confidence="high"))
    out = E.enrich(STUB)
    assert out["webpage"] == own and out["rejected_websites"][0]["reason"] == "Parent organization content"


def test_page_not_mentioning_the_org_is_not_judged(monkeypatch):
    fake(monkeypatch, ["https://x.org"], {"https://x.org": ("https://x.org", "Something else entirely " * 20)},
         judge=lambda t: (_ for _ in ()).throw(AssertionError("judged a page that does not mention the org")))
    out = E.enrich(STUB)
    assert "webpage" not in out and out["draft_description"] == "From search."


def test_redirect_stores_the_final_url(monkeypatch):
    redirect = "https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc"
    fake(monkeypatch, [redirect], {redirect: ("https://soildoctors.org/about", "Soil Doctors " * 30)})
    assert E.enrich(STUB)["webpage"] == "https://soildoctors.org/about"


def test_search_only_description_is_never_proposed(monkeypatch):
    fake(monkeypatch, [], {})
    out = E.enrich(STUB)
    assert "description" not in out and out["draft_description"] == "From search."


def test_legal_suffix_does_not_hide_a_mention():
    assert E.mentions("Welcome to Plan Conservation, a ranch planner", {"title": "Plan Conservation LLC"})


def test_failed_call_is_not_recorded(monkeypatch):
    fake(monkeypatch, [], {}, grounded=lambda: (_ for _ in ()).throw(RuntimeError("429")))
    assert E.enrich(STUB) is None


def test_reads_named_urls_and_search_sources(monkeypatch):
    chunk = types.SimpleNamespace(web=types.SimpleNamespace(uri="https://src.org"))
    response = types.SimpleNamespace(
        text="**WEBSITE:** [https://a.org/x](https://a.org/x)\nWEBSITE: <https://b.org>.\nWEBSITE: none",
        candidates=[types.SimpleNamespace(grounding_metadata=types.SimpleNamespace(grounding_chunks=[chunk]))])
    monkeypatch.setattr(E.G, "_generate_with_retry", lambda **k: response)
    assert E.find_websites("ctx")[0] == ["https://a.org/x", "https://b.org", "https://src.org"]


def test_after_max_judged_pages_falls_back_to_search(monkeypatch):
    urls = [f"https://p{i}.org" for i in range(5)]
    fake(monkeypatch, urls, {u: (u, "Soil Doctors " * 30) for u in urls},
         judge=lambda t: D(description="x", confidence="low", reason="Parent organization content"))
    out = E.enrich(STUB)
    assert len(out["rejected_websites"]) == E.MAX_JUDGED and out["draft_description"] == "From search."


def test_parent_line_is_read():
    assert E.parent("WEBSITE: https://a.org\n**PARENT:** Food and Agriculture Organization.") == \
        "Food and Agriculture Organization"
    assert E.parent("WEBSITE: none\nPARENT: none") is None
    assert E.parent("no parent line") is None


def test_parent_is_proposed(monkeypatch):
    fake(monkeypatch, [], {})
    monkeypatch.setattr(E, "find_websites", lambda ctx: ([], "WEBSITE: none\nPARENT: FAO"))
    assert E.enrich(STUB)["parent"] == "FAO"


def test_short_description_counts_as_incomplete():
    assert not E.complete({"webpage": "https://x.org", "description": "<p>Farmers.</p>"})
    assert not E.complete({"description": "A" * 100})
    assert E.complete({"webpage": "https://x.org", "description": "A" * 80})
