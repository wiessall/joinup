"""Serves the REST endpoints and a SPARQL endpoint over test/fixture.trig.

    python3 test/mock_server.py 8765 &
    SITE=http://localhost:8765 SPARQL=http://localhost:8765/sparql \
      COLLECTION=stf OUT=/tmp/out ./agent.sh

Only for checking agent.sh end to end without a site.
"""
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse
import json
import sys

from rdflib import Dataset

ds = Dataset(default_union=True)
ds.parse(Path(__file__).resolve().parent / "fixture.trig", format="trig")
ID = "https://sti-portal.fao.org/id/"
SOLUTIONS = [
    {"nid": 1, "uuid": "s1", "uri": ID + "source-record/s1", "title": "Solar pump (STF)"},
    {"nid": 3, "uuid": "s3", "uri": ID + "source-record/s3", "title": "Already curated"},
    {"nid": 4, "uuid": "s4", "uri": None, "title": "Never synced"},
]


class Handler(BaseHTTPRequestHandler):
    def _send(self, body, ctype="application/json", code=200):
        data = body if isinstance(body, bytes) else json.dumps(body).encode()
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/api/collection-solutions/v1/stf":
            return self._send(SOLUTIONS)
        if path == "/node/1":
            return self._send({"nid": [{"value": 1}], "title": [{"value": "Solar pump (STF)"}]})
        return self._send({"message": "not found"}, code=404)

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        form = parse_qs(self.rfile.read(length).decode())
        result = ds.query(form["query"][0])
        return self._send(result.serialize(format="json"), "application/sparql-results+json")

    def log_message(self, *args):
        pass


HTTPServer(("127.0.0.1", int(sys.argv[1])), Handler).serve_forever()
