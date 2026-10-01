#!/usr/bin/env bash
# Simulated agent: fetches raw Drupal data over REST and its semantic
# connections over SPARQL, writing every response as JSON to out/.
#
#   COLLECTION=fao-teca ./agent.sh            # first solution of the collection
#   COLLECTION=fao-teca SOLUTION_NID=123 ./agent.sh
#
# Needs curl and jq. See README.org for the preparation steps.
set -euo pipefail
# bash 5.2 treats & in ${var//pattern/replacement} as the match; URIs may contain it.
shopt -u patsub_replacement 2>/dev/null || true

SITE="${SITE:-https://digit-joinup-reference-2.ddev.site}"
SPARQL="${SPARQL:-http://digit-joinup-reference-2.ddev.site:8890/sparql}"
COLLECTION="${COLLECTION:?set COLLECTION to a collection nid, uuid or alias}"
SOLUTION_NID="${SOLUTION_NID:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"
OUT="${OUT:-$HERE/out}"
mkdir -p "$OUT"

say() { printf '\n== %s\n' "$*" >&2; }

# Runs a query file with __KEY__ placeholders substituted; JSON results out.
sparql() {
  local file="$1"; shift
  local query
  query="$(cat "$HERE/queries/$file")"
  while [ $# -gt 0 ]; do
    query="${query//__$1__/$2}"
    shift 2
  done
  curl -sS --fail-with-body -X POST "$SPARQL" \
    -H 'Accept: application/sparql-results+json' \
    --data-urlencode "query=$query"
}

say "1. REST: solutions of collection $COLLECTION"
curl -sS --fail-with-body "$SITE/api/collection-solutions/v1/$COLLECTION?_format=json" \
  > "$OUT/01-solutions.json"
jq -r 'length | "\(.) solution(s)"' "$OUT/01-solutions.json" >&2

say "1b. SPARQL: which of them has no ATIO innovation yet"
uris="$(jq -r '[.[] | select(.uri != null) | "<\(.uri)>"] | join(" ")' "$OUT/01-solutions.json")"
[ -n "$uris" ] || { echo "No solution in the list has a graph URI (never synced?)." >&2; exit 1; }
sparql 00-without-innovation.rq SOLUTIONS "$uris" > "$OUT/02-without-innovation.json"
jq -r '.results.bindings | length | "\(.) without an innovation"' "$OUT/02-without-innovation.json" >&2

if [ -n "$SOLUTION_NID" ]; then
  solution_uri="$(jq -r --argjson nid "$SOLUTION_NID" '.[] | select(.nid == $nid) | .uri' "$OUT/01-solutions.json")"
else
  solution_uri="$(jq -r '.results.bindings[0].solution.value // empty' "$OUT/02-without-innovation.json")"
fi
[ -n "$solution_uri" ] && [ "$solution_uri" != null ] || { echo "No solution to work on." >&2; exit 1; }
nid="$(jq -r --arg uri "$solution_uri" '.[] | select(.uri == $uri) | .nid' "$OUT/01-solutions.json")"

say "2. REST: full content of solution $nid ($solution_uri)"
curl -sS --fail-with-body "$SITE/node/$nid?_format=json" > "$OUT/03-solution.json"

say "3. SPARQL: its collection's claims and how they relate to ATIO fields"
sparql 01-claims-to-atio.rq SOLUTION "$solution_uri" > "$OUT/04-claims-to-atio.json"

say "4. SPARQL: solutions marked as the same innovation"
sparql 02-duplicates.rq SOLUTION "$solution_uri" > "$OUT/05-duplicates.json"
jq -r '.results.bindings[] | "   \(.duplicate.value)  \(.title.value // "")"' "$OUT/05-duplicates.json" >&2

say "5. SPARQL: duplicates' claims vs. the main claims and the ATIO fields"
sparql 03-duplicate-claims-map.rq SOLUTION "$solution_uri" > "$OUT/06-duplicate-claims-map.json"

say "6. SPARQL: candidate claims per ATIO field, weighted"
sparql 04-field-candidates.rq SOLUTION "$solution_uri" > "$OUT/07-field-candidates.json"

# The map, compacted: one entry per ATIO field, candidates by weight.
jq '[.results.bindings[]
     | {field: (.atioLabel.value // .atioField.value),
        weight: (.weight.value | tonumber),
        route: .route.value,
        role: .role.value,
        property: (.propertyLabel.value // .property.value),
        via: (.viaLabel.value // .via.value // null),
        relation: (.relation.value | sub(".*#"; "skos:")),
        relation2: ((.relation2.value // null) | if . then sub(".*#"; "skos:") else null end),
        value: .value.value,
        source: .source.value}]
    | group_by(.field)
    | map({field: .[0].field, candidates: (sort_by(-.weight) | map(del(.field)))})' \
  "$OUT/07-field-candidates.json" > "$OUT/08-field-map.json"

say "Done. The map: $OUT/08-field-map.json"
jq -r '.[] | "\(.field):", (.candidates[] | "   \(.weight)  \(.route)\t\(.role)\t\(.property) = \(.value)\(if .via then "  (via \(.via))" else "" end)")' \
  "$OUT/08-field-map.json" >&2
