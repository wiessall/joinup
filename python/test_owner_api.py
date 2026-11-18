"""Silent failures: a curator's decision overwritten by a new push, or AI values written outside the proposal."""

import json

from owner_api import build_proposal


def test_proposal_carries_fields_and_provenance_only():
    value = json.loads(build_proposal(None, {"uuid": "u", "webpage": "https://x.org", "description": "Text",
                                             "parent": "FAO", "model": "m", "evidence_url": "https://x.org"}))
    assert value["status"] == "pending"
    assert value["fields"] == {"webpage": "https://x.org", "parent": "FAO", "description": "Text"}
    assert value["model"] == "m" and "uuid" not in value


def test_decided_proposal_is_never_overwritten():
    for status in ("reviewed", "rejected"):
        assert build_proposal(json.dumps({"status": status, "fields": {}}), {"webpage": "https://x.org"}) is None


def test_pending_is_replaced_and_draft_description_proposed():
    value = json.loads(build_proposal(json.dumps({"status": "pending", "fields": {}}), {"draft_description": "Draft"}))
    assert value["fields"] == {"description": "Draft"}


def test_nothing_to_propose():
    assert build_proposal(None, {"uuid": "u", "rejected_websites": []}) is None
