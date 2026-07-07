#!/usr/bin/env python3
"""
trig_visualizer.py
──────────────────
Renders a TRiG file as an interactive HTML graph where:
  • Each named graph is a colour-coded cluster / group
  • Nodes shared across graphs appear in every relevant cluster
  • Cross-graph edges (predicates whose domain/range span graphs) are drawn
    as inter-cluster arrows
  • The default graph is shown as its own cluster

Usage:
    python trig_visualizer.py <file.trig> [output.html]

If no output path is given, <file>.html is written next to the input.
"""

import sys
import re
import hashlib
from pathlib import Path
from collections import defaultdict

from rdflib import ConjunctiveGraph, URIRef, BNode, Literal, Graph
from rdflib.namespace import RDF, RDFS, OWL

try:
    from pyvis.network import Network
except ImportError:
    sys.exit("pyvis not found — run: pip install pyvis")


# ── Colour palette for graphs (cycles if more than len) ──────────────────────
PALETTE = [
    "#4e79a7", "#f28e2b", "#e15759", "#76b7b2",
    "#59a14f", "#edc948", "#b07aa1", "#ff9da7",
    "#9c755f", "#bab0ac",
]

DEFAULT_GRAPH_LABEL = "(default graph)"


def shorten(uri, ns_map):
    """Return a human-readable prefixed name or local name for a URI."""
    uri_str = str(uri)
    for prefix, ns in sorted(ns_map.items(), key=lambda x: -len(str(x[1]))):
        ns_str = str(ns)
        if uri_str.startswith(ns_str):
            local = uri_str[len(ns_str):]
            if local:
                return f"{prefix}:{local}" if prefix else local
    # Fall back to fragment or last path segment
    if "#" in uri_str:
        return uri_str.split("#")[-1]
    return uri_str.rstrip("/").split("/")[-1] or uri_str


def node_id(term):
    """Stable string ID for a node (URI, blank node, or literal)."""
    return hashlib.md5(str(term).encode()).hexdigest()[:12]


def node_label(term, ns_map):
    if isinstance(term, URIRef):
        return shorten(term, ns_map)
    if isinstance(term, BNode):
        return f"_:{str(term)[:6]}"
    # Literal — truncate if long
    s = str(term)
    return f'"{s[:40]}{"…" if len(s) > 40 else ""}"'


def node_shape(term, g):
    """Pick a visual shape based on rdf:type."""
    if isinstance(term, Literal):
        return "box"
    if isinstance(term, BNode):
        return "ellipse"
    types = set(g.objects(term, RDF.type))
    if OWL.Class in types or RDFS.Class in types:
        return "diamond"
    if OWL.ObjectProperty in types or OWL.DatatypeProperty in types or RDF.Property in types:
        return "triangle"
    return "dot"


def build_graph(trig_path: Path, output_path: Path):
    cg = ConjunctiveGraph()
    cg.parse(str(trig_path), format="trig")

    ns_map = dict(cg.namespaces())

    # Collect all graphs (named + default)
    graph_iris = [ctx.identifier for ctx in cg.contexts()]
    named_graphs = [g for g in graph_iris if isinstance(g, URIRef)]
    default_graphs = [g for g in graph_iris if isinstance(g, BNode)]

    graph_labels = {}
    graph_colors = {}
    for i, gid in enumerate(named_graphs):
        graph_labels[gid] = shorten(gid, ns_map)
        graph_colors[gid] = PALETTE[i % len(PALETTE)]
    for i, gid in enumerate(default_graphs):
        graph_labels[gid] = DEFAULT_GRAPH_LABEL
        graph_colors[gid] = "#cccccc"

    all_graph_ids = named_graphs + default_graphs

    # Map: node_id → set of graph IRIs it appears in
    node_graphs: dict[str, set] = defaultdict(set)
    # Map: node_id → (term, label, shape)
    node_info: dict = {}
    # Edges within each graph: list of (src_id, dst_id, pred_label, graph_id)
    intra_edges = []
    # Cross-graph edges: predicate whose subj is in graph A, obj is in graph B
    cross_edges = []

    # Flat union graph for type lookups
    union = Graph()
    for ctx in cg.contexts():
        for t in ctx:
            union.add(t)

    for ctx in cg.contexts():
        gid = ctx.identifier
        if gid not in all_graph_ids:
            continue
        for s, p, o in ctx:
            sid = node_id(s)
            oid = node_id(o)
            plabel = node_label(p, ns_map)

            node_graphs[sid].add(gid)
            node_graphs[oid].add(gid)

            if sid not in node_info:
                node_info[sid] = (s, node_label(s, ns_map), node_shape(s, union))
            if oid not in node_info:
                node_info[oid] = (o, node_label(o, ns_map), node_shape(o, union))

            intra_edges.append((sid, oid, plabel, gid))

    # Detect cross-graph pairs: nodes that appear in >1 graph
    cross_graph_nodes = {nid for nid, gs in node_graphs.items() if len(gs) > 1}

    # ── Build pyvis network ───────────────────────────────────────────────────
    net = Network(
        height="92vh",
        width="100%",
        bgcolor="#1a1a2e",
        font_color="#e0e0e0",
        directed=True,
        notebook=False,
    )
    net.set_options("""
    {
      "physics": {
        "forceAtlas2Based": {
          "gravitationalConstant": -80,
          "centralGravity": 0.005,
          "springLength": 120,
          "springConstant": 0.08,
          "damping": 0.6
        },
        "solver": "forceAtlas2Based",
        "stabilization": { "iterations": 200 }
      },
      "edges": {
        "arrows": { "to": { "enabled": true, "scaleFactor": 0.6 } },
        "smooth": { "type": "curvedCW", "roundness": 0.15 },
        "font": { "size": 9, "color": "#aaaaaa", "strokeWidth": 0 }
      },
      "nodes": {
        "font": { "size": 11 }
      },
      "interaction": {
        "hover": true,
        "tooltipDelay": 100,
        "navigationButtons": true
      }
    }
    """)

    added_nodes: set[str] = set()

    def add_node(nid, gid):
        if nid in added_nodes:
            return
        term, label, shape = node_info[nid]
        color = graph_colors.get(gid, "#888888")
        border = "#ffffff" if nid in cross_graph_nodes else color
        is_cross = nid in cross_graph_nodes
        size = 18 if is_cross else 12
        tooltip = str(term) if isinstance(term, URIRef) else label
        net.add_node(
            nid,
            label=label,
            title=tooltip,
            color={"background": color, "border": border,
                   "highlight": {"background": "#ffffff", "border": color}},
            shape=shape,
            size=size,
            borderWidth=3 if is_cross else 1,
        )
        added_nodes.add(nid)

    # Add nodes and intra-graph edges
    for sid, oid, plabel, gid in intra_edges:
        add_node(sid, gid)
        add_node(oid, gid)
        # Cross-graph edge if the two nodes come from different graphs
        s_graphs = node_graphs[sid] - {gid}
        o_graphs = node_graphs[oid] - {gid}
        is_cross_edge = bool(s_graphs or o_graphs)
        net.add_edge(
            sid, oid,
            label=plabel,
            color={"color": graph_colors.get(gid, "#888"), "opacity": 0.7},
            dashes=is_cross_edge,
            width=2 if is_cross_edge else 1,
        )

    # ── Legend & title HTML injected into the page ────────────────────────────
    legend_items = ""
    for gid in all_graph_ids:
        color = graph_colors[gid]
        label = graph_labels[gid]
        triple_count = sum(1 for _ in cg.get_context(gid))
        legend_items += (
            f'<div class="legend-item">'
            f'<span class="swatch" style="background:{color}"></span>'
            f'<span class="gname">{label}</span>'
            f'<span class="count">{triple_count} triples</span>'
            f'</div>\n'
        )

    source_name = trig_path.name
    total_triples = sum(1 for _ in cg)
    total_graphs = len(all_graph_ids)

    # Generate the pyvis HTML
    raw_html = net.generate_html()

    # Inject our panel into the <body>
    panel = f"""
<div id="overlay">
  <div id="legend">
    <div id="legend-title">
      <strong>📊 {source_name}</strong>
      <div class="meta">{total_graphs} graphs · {total_triples} triples</div>
    </div>
    {legend_items}
    <div class="hint">
      ◆ class &nbsp;▲ property &nbsp;● instance<br>
      <span style="border:2px solid #fff;padding:0 4px;border-radius:3px;font-size:10px">thick border</span> = shared across graphs<br>
      <span style="opacity:.6">- - -</span> dashed = cross-graph edge
    </div>
  </div>
</div>
<style>
  #overlay {{
    position: fixed; top: 12px; right: 12px; z-index: 9999;
    font-family: 'Segoe UI', sans-serif;
  }}
  #legend {{
    background: rgba(10,10,30,0.88);
    border: 1px solid #444;
    border-radius: 8px;
    padding: 12px 14px;
    min-width: 210px;
    max-width: 260px;
    backdrop-filter: blur(4px);
  }}
  #legend-title {{ margin-bottom: 10px; color: #e0e0e0; font-size: 13px; }}
  .meta {{ font-size: 11px; color: #888; margin-top: 2px; }}
  .legend-item {{
    display: flex; align-items: center; gap: 8px;
    margin: 5px 0; font-size: 12px; color: #ccc;
  }}
  .swatch {{
    width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0;
  }}
  .gname {{ flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }}
  .count {{ font-size: 10px; color: #666; white-space: nowrap; }}
  .hint {{ font-size: 10px; color: #666; margin-top: 10px; line-height: 1.6; }}
</style>
"""

    utils_stub = """<script>
// pyvis utils stub (inlined for portability)
var neighbourhoodHighlight = function(params, network, allNodes, allEdges) {};
var filterHighlight = function(params, network, allNodes, allEdges) {};
var selectNode = function(params, network, allNodes) { return allNodes; };
</script>"""

    final_html = (
        raw_html
        .replace('<script src="lib/bindings/utils.js"></script>', utils_stub)
        .replace('<script src="../node_modules/vis/dist/vis.js"></script>', '')
        .replace("</body>", panel + "\n</body>")
    )
    output_path.write_text(final_html, encoding="utf-8")
    print(f"✓ Written to {output_path}")
    print(f"  {total_graphs} graphs, {total_triples} triples, {len(node_info)} nodes")


def main():
    if len(sys.argv) < 2:
        print("Usage: python trig_visualizer.py <file.trig> [output.html]")
        sys.exit(1)

    trig_path = Path(sys.argv[1])
    if not trig_path.exists():
        sys.exit(f"File not found: {trig_path}")

    output_path = Path(sys.argv[2]) if len(sys.argv) > 2 else trig_path.with_suffix(".html")
    build_graph(trig_path, output_path)


if __name__ == "__main__":
    main()
