#!/usr/bin/env python3
"""Build and check the split sti_atio ontology.

    python3 build.py                 # base profile  -> dist/sti-atio-base.ttl
    python3 build.py --profile all   # every folder  -> dist/sti-atio-all.ttl
    python3 build.py --strict        # exit 1 on any finding (for CI)

Checks, in order of how quietly they fail:
  1. every file parses on its own (the "each class file is self-contained" rule)
  2. no term is declared in two files (the v8 claimSubject / aiGeneratedDescription bug)
  3. dependency report: sti_atio terms the profile references but does not
     declare.  For `base` this is the list of base -> non-base dependencies,
     i.e. things to cut or re-home before base can ship on its own.
  4. inference traps: runs OWL-RL over the build plus two innocent fixtures and
     reports any that get typed as a sti_atio class they should not be.

Requires: pip install rdflib owlrl
"""
from __future__ import annotations

import argparse
import collections
import pathlib
import sys

import rdflib
from rdflib.namespace import OWL, RDF, RDFS

ROOT = pathlib.Path(__file__).resolve().parent
NS = rdflib.Namespace('https://sti-portal.fao.org/ontology/sti-atio#')

PROFILES = {
    # MVP: what we intend to port.  Order matters only for readability of dist/.
    'base': ['ontology.ttl', 'base_classes'],
    'all': ['ontology.ttl', 'base_classes', 'advanced_classes',
            'definitions_relative_to_outside', 'ai_qa_edit_tracking',
            'dont_understand', 'probably_wont_implement', 'utils', 'shapes'],
}

# Resources that are not ontology terms: instance data in examples, graph IRIs.
NOT_TERMS = ('#scheme/', '#graph/', '#rule/', '#shapes/')

# Fixtures that must stay what they are.  An organisation with a name, and a
# plain SKOS concept aligned to another one.
FIXTURES = """
@prefix schema: <https://schema.org/> .
@prefix skos:   <http://www.w3.org/2004/02/skos/core#> .
@prefix org:    <http://www.w3.org/ns/org#> .
<urn:fixture:org>      a org:Organization ; schema:name "Some organisation" .
<urn:fixture:conceptA> a skos:Concept ; skos:exactMatch <urn:fixture:conceptB> .
"""


def files(profile: str) -> list[pathlib.Path]:
    out = []
    for entry in PROFILES[profile]:
        p = ROOT / entry
        out += [p] if p.is_file() else sorted(p.rglob('*.ttl'))
    return out


def rel(p: pathlib.Path) -> str:
    return str(p.relative_to(ROOT))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('--profile', choices=PROFILES, default='base')
    ap.add_argument('--strict', action='store_true')
    args = ap.parse_args()

    findings = 0
    merged = rdflib.Graph()
    merged.bind('sti_atio', NS)
    declared_in: dict[rdflib.URIRef, set[str]] = collections.defaultdict(set)

    # 1. standalone parse
    for f in files(args.profile):
        g = rdflib.Graph()
        try:
            g.parse(f, format='turtle')
        except Exception as exc:  # rdflib raises several unrelated types
            print(f'PARSE  {rel(f)}: {exc}')
            findings += 1
            continue
        for s in set(g.subjects(RDF.type, None)):
            if isinstance(s, rdflib.URIRef) and str(s).startswith(str(NS)):
                declared_in[s].add(rel(f))
        merged += g

    # 2. duplicate declarations
    for term, where in sorted(declared_in.items()):
        if len(where) > 1:
            print(f'DUPLICATE  {merged.qname(term)} declared in {sorted(where)}')
            findings += 1

    # 3. dependency report
    used = collections.defaultdict(set)
    for s, p, o in merged:
        for node in (s, p, o):
            if isinstance(node, rdflib.URIRef) and str(node).startswith(str(NS)) \
                    and not any(k in str(node) for k in NOT_TERMS):
                used[node].add(merged.qname(s) if isinstance(s, rdflib.URIRef) else '_:b')
    missing = {t: u for t, u in used.items()
               if (t, RDF.type, None) not in merged}
    for term, users in sorted(missing.items()):
        print(f'UNDECLARED  {merged.qname(term)}  <- used by {", ".join(sorted(users)[:4])}')
        findings += 1

    # 4. inference traps
    try:
        import owlrl
    except ImportError:
        print('SKIP  owlrl not installed; inference-trap check not run')
    else:
        g = rdflib.Graph()
        g += merged
        g.parse(data=FIXTURES, format='turtle')
        owlrl.DeductiveClosure(owlrl.OWLRL_Semantics).expand(g)
        suspects = [rdflib.URIRef('urn:fixture:org'), rdflib.URIRef('urn:fixture:conceptA'),
                    rdflib.URIRef('https://sti-portal.fao.org/ontology/sti-atio')]
        for s in suspects:
            wrong = sorted(merged.qname(c) for c in g.objects(s, RDF.type)
                           if str(c).startswith(str(NS)))
            if wrong:
                print(f'TRAP  {s} is inferred to be {wrong}')
                findings += 1

    out = ROOT / 'dist' / f'sti-atio-{args.profile}.ttl'
    out.parent.mkdir(exist_ok=True)
    merged.serialize(out, format='turtle')
    print(f'\n{len(merged)} triples -> {rel(out)}; {findings} finding(s)')
    return 1 if (args.strict and findings) else 0


if __name__ == '__main__':
    sys.exit(main())
