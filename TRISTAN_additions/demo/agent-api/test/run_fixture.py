"""Runs the demo queries against test/fixture.trig with rdflib and checks them.

    python3 test/run_fixture.py

rdflib evaluates standard SPARQL 1.1; Virtuoso accepts the same queries. This
catches wrong joins and wrong weights before the demo, not Virtuoso quirks.
"""
from pathlib import Path
import sys

from rdflib import Dataset

HERE = Path(__file__).resolve().parent
QUERIES = HERE.parent / "queries"
ID = "https://sti-portal.fao.org/id/"
ATIO = "https://sti-portal.fao.org/atio#"
S1 = ID + "source-record/s1"


def run(name, **subs):
    text = (QUERIES / name).read_text()
    for key, value in subs.items():
        text = text.replace(f"__{key}__", value)
    rows = []
    for row in ds.query(text):
        rows.append({k: (str(v) if v is not None else None) for k, v in row.asdict().items()})
    return rows


ds = Dataset(default_union=True)
ds.parse(HERE / "fixture.trig", format="trig")
failures = []


def check(label, condition):
    print(("ok   " if condition else "FAIL ") + label)
    if not condition:
        failures.append(label)


rows = run("00-without-innovation.rq", SOLUTIONS=" ".join(f"<{ID}source-record/{s}>" for s in ("s1", "s2", "s3")))
check("00: s3 has an innovation, s1 and s2 do not",
      sorted(r["solution"] for r in rows) == [ID + "source-record/s1", ID + "source-record/s2"])

rows = run("01-claims-to-atio.rq", SOLUTION=S1)
by = {(r["propertyLabel"], r["route"], r["atioField"]): r for r in rows}
check("01: sameInnovationAs claim is not listed as a field claim",
      all(r["property"] != "https://sti-portal.fao.org/sti#sameInnovationAs" for r in rows))
check("01: readiness -> readinessLevel direct, weight 1.0",
      float(by[("technical_readiness", "direct", ATIO + "readinessLevel")]["weight"]) == 1.0)
check("01: country -> originCountry indirect via eca origin, 0.4 * 1.0",
      abs(float(by[("country", "indirect", ATIO + "originCountry")]["weight"]) - 0.4) < 1e-9)
check("01: values resolved to labels", {r["value"] for r in rows} == {"TRL 6", "Kenya"})

rows = run("02-duplicates.rq", SOLUTION=S1)
check("02: s2 is the one duplicate, stated by stf",
      [(r["duplicate"], r["statedBy"]) for r in rows] == [(ID + "source-record/s2", ID + "collection/stf")])

rows = run("03-duplicate-claims-map.rq", SOLUTION=S1)
maturity = [r for r in rows if r["dupPropertyLabel"] == "Development stage"]
check("03: eca maturity closeMatch stf readiness (authored the other way round)",
      len(maturity) == 1 and maturity[0]["mainPropertyLabel"] == "technical_readiness"
      and float(maturity[0]["ccWeight"]) == 0.8 and maturity[0]["mainValue"] == "TRL 6")
origin = [r for r in rows if r["dupPropertyLabel"] == "Country of origin"]
check("03: eca origin exactMatch originCountry and relatedMatch stf country",
      len(origin) == 1 and origin[0]["atioField"] == ATIO + "originCountry"
      and float(origin[0]["atioWeight"]) == 1.0 and origin[0]["mainPropertyLabel"] == "country")

rows = run("04-field-candidates.rq", SOLUTION=S1)
readiness = [(r["role"], r["route"], round(float(r["weight"]), 2), r["value"])
             for r in rows if r["atioField"] == ATIO + "readinessLevel"]
check("04: readinessLevel ranked: own TRL 6 (1.0), then eca Pilot via stf readiness (0.8)",
      readiness == [("self", "direct", 1.0, "TRL 6"), ("duplicate", "indirect", 0.8, "Pilot")])
origin = [(r["role"], r["route"], round(float(r["weight"]), 2), r["value"])
          for r in rows if r["atioField"] == ATIO + "originCountry"]
check("04: originCountry ranked: eca Kenya (1.0), then own Kenya via eca origin (0.4)",
      origin == [("duplicate", "direct", 1.0, "Kenya"), ("self", "indirect", 0.4, "Kenya")])

rows = run("10-semantic-map.rq")
check("10: all four mappings listed, two of each kind",
      sorted(r["kind"] for r in rows) == ["property to ATIO field"] * 2 + ["property to property"] * 2
      and all(r["fromLabel"] and r["toLabel"] for r in rows))

STF, ECA = ID + "collection/stf", ID + "collection/eca"
rows = run("11-collections-compared.rq", COLLECTION_A=STF, COLLECTION_B=ECA)
check("11: stf vs eca: readiness~maturity (0.8), country~origin (0.4), both direct, authored either way",
      [(r["labelA"], r["route"], float(r["weight"]), r["labelB"]) for r in rows]
      == [("technical_readiness", "direct", 0.8, "Development stage"),
          ("country", "direct", 0.4, "Country of origin")])
check("11: a collection compared with itself has no rows here",
      run("11-collections-compared.rq", COLLECTION_A=STF, COLLECTION_B=STF) == [])

rows = run("12-same-as-pairs.rq")
check("12: s1 and s2 are one pair, listed once",
      [(r["recordA"], r["recordB"], r["collectionA"], r["collectionB"]) for r in rows]
      == [(S1, ID + "source-record/s2", STF, ECA)])

# Last, because it changes the data: eca maturity also maps to the ATIO field
# stf readiness maps to, so the two meet there as well.
from rdflib import URIRef
ds.graph(URIRef("https://sti-portal.fao.org/graph/mappings")).add((
    URIRef(ID + "property/eca-maturity"),
    URIRef("http://www.w3.org/2004/02/skos/core#closeMatch"),
    URIRef(ATIO + "readinessLevel")))
rows = run("11-collections-compared.rq", COLLECTION_A=STF, COLLECTION_B=ECA)
via = [r for r in rows if r["route"] == "via ATIO field"]
check("11: shared ATIO field route: exact (1.0) * close (0.8) = 0.8 via Readiness level",
      len(via) == 1 and abs(float(via[0]["weight"]) - 0.8) < 1e-9
      and via[0]["viaLabel"] == "Readiness level" and via[0]["labelB"] == "Development stage")

print(f"\n{len(failures)} failure(s)")
sys.exit(1 if failures else 0)
