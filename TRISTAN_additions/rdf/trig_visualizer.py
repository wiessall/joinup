#!/home/tristan/.local/share/mamba/envs/rdf-tools/bin/python
"""
trig_visualizer.py
──────────────────
Renders a TRiG file as an interactive HTML graph.

Legend panel lets you toggle:
  • Individual named graphs on/off
  • Node types: Classes, Properties, Instances, Literals, Blank nodes
  • Edge types: Within-graph, Cross-graph

Usage:
    python trig_visualizer.py <file.trig> [output.html]
"""

import sys
import json
import hashlib
from pathlib import Path
from collections import defaultdict

from rdflib import ConjunctiveGraph, URIRef, BNode, Literal, Graph
from rdflib.namespace import RDF, RDFS, OWL

try:
    from pyvis.network import Network
except ImportError:
    sys.exit("pyvis not found — run: pip install pyvis")


PALETTE = [
    "#4e79a7", "#f28e2b", "#e15759", "#76b7b2",
    "#59a14f", "#edc948", "#b07aa1", "#ff9da7",
    "#9c755f", "#bab0ac",
]
DEFAULT_GRAPH_LABEL = "(default graph)"


def shorten(uri, ns_map):
    uri_str = str(uri)
    for prefix, ns in sorted(ns_map.items(), key=lambda x: -len(str(x[1]))):
        ns_str = str(ns)
        if uri_str.startswith(ns_str):
            local = uri_str[len(ns_str):]
            if local:
                return f"{prefix}:{local}" if prefix else local
    if "#" in uri_str:
        return uri_str.split("#")[-1]
    return uri_str.rstrip("/").split("/")[-1] or uri_str


def node_id(term):
    return hashlib.md5(str(term).encode()).hexdigest()[:12]


def edge_id(sid, oid, plabel, gid):
    key = f"{sid}|{oid}|{plabel}|{gid}"
    return "e" + hashlib.md5(key.encode()).hexdigest()[:10]


def node_label(term, ns_map):
    if isinstance(term, URIRef):
        return shorten(term, ns_map)
    if isinstance(term, BNode):
        return f"_:{str(term)[:6]}"
    s = str(term)
    return f'"{s[:40]}{"…" if len(s) > 40 else ""}"'


def node_type(term, union_graph):
    """Return one of: class | property | instance | literal | blank"""
    if isinstance(term, Literal):
        return "literal"
    if isinstance(term, BNode):
        return "blank"
    types = set(union_graph.objects(term, RDF.type))
    if OWL.Class in types or RDFS.Class in types:
        return "class"
    if OWL.ObjectProperty in types or OWL.DatatypeProperty in types or RDF.Property in types:
        return "property"
    return "instance"


NODE_SHAPES = {
    "class":    "diamond",
    "property": "triangle",
    "instance": "dot",
    "literal":  "box",
    "blank":    "ellipse",
}


def build_graph(trig_path: Path, output_path: Path):
    cg = ConjunctiveGraph()
    cg.parse(str(trig_path), format="trig")
    ns_map = dict(cg.namespaces())

    graph_iris   = [ctx.identifier for ctx in cg.contexts()]
    named_graphs = [g for g in graph_iris if isinstance(g, URIRef)]
    default_graphs = [g for g in graph_iris if isinstance(g, BNode)]
    all_graph_ids = named_graphs + default_graphs

    graph_labels = {}
    graph_colors = {}
    for i, gid in enumerate(named_graphs):
        graph_labels[gid] = shorten(gid, ns_map)
        graph_colors[gid] = PALETTE[i % len(PALETTE)]
    for gid in default_graphs:
        graph_labels[gid] = DEFAULT_GRAPH_LABEL
        graph_colors[gid] = "#aaaaaa"

    # Build union graph for type lookups
    union = Graph()
    for ctx in cg.contexts():
        for t in ctx:
            union.add(t)

    node_graphs: dict[str, set] = defaultdict(set)
    node_info: dict = {}
    all_edges = []   # (eid, sid, oid, plabel, gid)

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
                node_info[sid] = (s, node_label(s, ns_map), node_type(s, union))
            if oid not in node_info:
                node_info[oid] = (o, node_label(o, ns_map), node_type(o, union))
            eid = edge_id(sid, oid, plabel, str(gid))
            all_edges.append((eid, sid, oid, plabel, gid))

    cross_graph_nodes = {nid for nid, gs in node_graphs.items() if len(gs) > 1}

    # ── pyvis network ────────────────────────────────────────────────────────
    net = Network(height="92vh", width="100%", bgcolor="#1a1a2e",
                  font_color="#e0e0e0", directed=True, notebook=False)
    net.set_options("""{
      "physics": {
        "forceAtlas2Based": {
          "gravitationalConstant": -80, "centralGravity": 0.005,
          "springLength": 120, "springConstant": 0.08, "damping": 0.6
        },
        "solver": "forceAtlas2Based",
        "stabilization": { "iterations": 200 }
      },
      "edges": {
        "arrows": { "to": { "enabled": true, "scaleFactor": 0.6 } },
        "smooth": { "type": "curvedCW", "roundness": 0.15 },
        "font": { "size": 9, "color": "#aaaaaa", "strokeWidth": 0 }
      },
      "nodes": { "font": { "size": 11 } },
      "interaction": { "hover": true, "tooltipDelay": 100, "navigationButtons": true }
    }""")

    added_nodes: set[str] = set()

    def add_node(nid, primary_gid):
        if nid in added_nodes:
            return
        term, label, ntype = node_info[nid]
        color = graph_colors.get(primary_gid, "#888888")
        is_cross = nid in cross_graph_nodes
        tooltip = str(term) if isinstance(term, URIRef) else label
        net.add_node(
            nid, label=label, title=tooltip,
            color={"background": color,
                   "border": "#ffffff" if is_cross else color,
                   "highlight": {"background": "#ffffff", "border": color}},
            shape=NODE_SHAPES.get(ntype, "dot"),
            size=18 if is_cross else 12,
            borderWidth=3 if is_cross else 1,
        )
        added_nodes.add(nid)

    # Deduplicate edges (same eid = same triple in same graph)
    seen_eids: set[str] = set()
    for eid, sid, oid, plabel, gid in all_edges:
        add_node(sid, gid)
        add_node(oid, gid)
        if eid in seen_eids:
            continue
        seen_eids.add(eid)
        is_cross_edge = bool((node_graphs[sid] | node_graphs[oid]) - {gid})
        net.add_edge(
            sid, oid, id=eid, label=plabel,
            color={"color": graph_colors.get(gid, "#888"), "opacity": 0.7},
            dashes=is_cross_edge,
            width=2 if is_cross_edge else 1,
        )

    # ── Metadata for JS filter engine ────────────────────────────────────────
    # node_meta: {id: {graphs: [label,...], type: str}}
    node_meta = {}
    for nid, (term, label, ntype) in node_info.items():
        node_meta[nid] = {
            "graphs": [graph_labels[g] for g in node_graphs[nid] if g in graph_labels],
            "type": ntype,
        }

    # edge_meta: {eid: {graph: label, isCross: bool, src: id, dst: id}}
    edge_meta = {}
    for eid, sid, oid, plabel, gid in all_edges:
        if eid not in edge_meta:
            is_cross_edge = bool((node_graphs[sid] | node_graphs[oid]) - {gid})
            edge_meta[eid] = {
                "graph": graph_labels.get(gid, DEFAULT_GRAPH_LABEL),
                "isCross": is_cross_edge,
                "src": sid,
                "dst": oid,
            }

    graph_info_for_js = [
        {"id": graph_labels[gid], "color": graph_colors[gid],
         "count": sum(1 for _ in cg.get_context(gid))}
        for gid in all_graph_ids
    ]

    source_name = trig_path.name
    total_triples = sum(1 for _ in cg)
    total_graphs = len(all_graph_ids)

    # ── Build the filter panel HTML + JS ─────────────────────────────────────
    graph_btns = ""
    for g in graph_info_for_js:
        graph_btns += (
            f'<button class="toggle-btn active graph-btn" '
            f'data-graph="{g["id"]}" '
            f'style="--btn-color:{g["color"]}">'
            f'<span class="btn-dot"></span>'
            f'<span class="btn-label">{g["id"]}</span>'
            f'<span class="btn-count">{g["count"]}</span>'
            f'</button>\n'
        )

    panel = f"""
<div id="overlay">
  <div id="legend">
    <div id="legend-header">
      <strong>📊 {source_name}</strong>
      <div class="meta">{total_graphs} graphs · {total_triples} triples</div>
    </div>

    <div class="section-label">GRAPHS</div>
    <div id="graph-toggles">{graph_btns}</div>

    <div class="section-label">NODE TYPES</div>
    <div id="type-toggles">
      <button class="toggle-btn active type-btn" data-type="class">◆ Classes</button>
      <button class="toggle-btn active type-btn" data-type="property">▲ Properties</button>
      <button class="toggle-btn active type-btn" data-type="instance">● Instances</button>
      <button class="toggle-btn active type-btn" data-type="literal">▪ Literals</button>
      <button class="toggle-btn active type-btn" data-type="blank">○ Blank nodes</button>
    </div>

    <div class="section-label">EDGES</div>
    <div id="edge-toggles">
      <button class="toggle-btn active edge-btn" data-edge="intra">― Within graph</button>
      <button class="toggle-btn active edge-btn" data-edge="cross">╌ Cross-graph</button>
    </div>

    <div class="hint">Thick border = shared across graphs</div>
  </div>
</div>

<style>
  #overlay {{
    position: fixed; top: 12px; right: 12px; z-index: 9999;
    font-family: 'Segoe UI', system-ui, sans-serif;
  }}
  #legend {{
    background: rgba(10,10,30,0.92);
    border: 1px solid #333;
    border-radius: 10px;
    padding: 12px 13px;
    width: 230px;
    backdrop-filter: blur(6px);
    max-height: 94vh;
    overflow-y: auto;
  }}
  #legend-header {{ margin-bottom: 10px; color: #e0e0e0; font-size: 13px; }}
  .meta {{ font-size: 11px; color: #777; margin-top: 2px; }}
  .section-label {{
    font-size: 9px; font-weight: 700; letter-spacing: .1em;
    color: #555; margin: 10px 0 5px; text-transform: uppercase;
  }}
  #graph-toggles, #type-toggles, #edge-toggles {{
    display: flex; flex-direction: column; gap: 4px;
  }}
  .toggle-btn {{
    display: flex; align-items: center; gap: 7px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #ccc; font-size: 11px; padding: 4px 8px;
    cursor: pointer; text-align: left;
    transition: opacity 0.15s, background 0.15s;
    width: 100%;
  }}
  .toggle-btn:hover {{ background: rgba(255,255,255,0.1); }}
  .toggle-btn.inactive {{ opacity: 0.35; }}
  .btn-dot {{
    width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0;
    background: var(--btn-color, #888);
  }}
  .btn-label {{ flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }}
  .btn-count {{ font-size: 9px; color: #555; white-space: nowrap; }}
  .hint {{ font-size: 10px; color: #555; margin-top: 10px; line-height: 1.5; }}
</style>

<script>
(function() {{
  const nodeMeta = {json.dumps(node_meta)};
  const edgeMeta = {json.dumps(edge_meta)};

  const state = {{
    graphs:  new Set({json.dumps([g["id"] for g in graph_info_for_js])}),
    types:   new Set(["class","property","instance","literal","blank"]),
    intra:   true,
    cross:   true,
  }};

  function applyFilters() {{
    if (typeof network === 'undefined') {{ setTimeout(applyFilters, 200); return; }}

    const nodeUpdates = [];
    for (const [id, meta] of Object.entries(nodeMeta)) {{
      const graphOk  = meta.graphs.some(g => state.graphs.has(g));
      const typeOk   = state.types.has(meta.type);
      nodeUpdates.push({{ id, hidden: !(graphOk && typeOk) }});
    }}
    network.body.data.nodes.update(nodeUpdates);

    const edgeUpdates = [];
    for (const [id, meta] of Object.entries(edgeMeta)) {{
      const graphOk  = state.graphs.has(meta.graph);
      const crossOk  = meta.isCross ? state.cross : state.intra;
      const srcMeta  = nodeMeta[meta.src];
      const dstMeta  = nodeMeta[meta.dst];
      const srcOk    = srcMeta && srcMeta.graphs.some(g => state.graphs.has(g)) && state.types.has(srcMeta.type);
      const dstOk    = dstMeta && dstMeta.graphs.some(g => state.graphs.has(g)) && state.types.has(dstMeta.type);
      edgeUpdates.push({{ id, hidden: !(graphOk && crossOk && srcOk && dstOk) }});
    }}
    network.body.data.edges.update(edgeUpdates);
  }}

  // Wire up graph buttons
  document.querySelectorAll('.graph-btn').forEach(btn => {{
    btn.addEventListener('click', () => {{
      const g = btn.dataset.graph;
      if (state.graphs.has(g)) {{ state.graphs.delete(g); btn.classList.add('inactive'); }}
      else                     {{ state.graphs.add(g);    btn.classList.remove('inactive'); }}
      applyFilters();
    }});
  }});

  // Wire up node-type buttons
  document.querySelectorAll('.type-btn').forEach(btn => {{
    btn.addEventListener('click', () => {{
      const t = btn.dataset.type;
      if (state.types.has(t)) {{ state.types.delete(t); btn.classList.add('inactive'); }}
      else                    {{ state.types.add(t);    btn.classList.remove('inactive'); }}
      applyFilters();
    }});
  }});

  // Wire up edge buttons
  document.querySelectorAll('.edge-btn').forEach(btn => {{
    btn.addEventListener('click', () => {{
      const e = btn.dataset.edge;
      if (e === 'intra') {{ state.intra = !state.intra; btn.classList.toggle('inactive', !state.intra); }}
      if (e === 'cross') {{ state.cross = !state.cross; btn.classList.toggle('inactive', !state.cross); }}
      applyFilters();
    }});
  }});

  // Wait for vis-network to be ready before first filter pass
  window.addEventListener('load', () => setTimeout(applyFilters, 500));
}})();
</script>
"""

    utils_stub = """<script>
var neighbourhoodHighlight = function(params, network, allNodes, allEdges) {};
var filterHighlight = function(params, network, allNodes, allEdges) {};
var selectNode = function(params, network, allNodes) { return allNodes; };
</script>"""

    raw_html = net.generate_html()
    final_html = (
        raw_html
        .replace('<script src="lib/bindings/utils.js"></script>', utils_stub)
        .replace('<script src="../node_modules/vis/dist/vis.js"></script>', '')
        .replace("</body>", panel + "\n</body>")
    )
    output_path.write_text(final_html, encoding="utf-8")
    print(f"✓ Written to {output_path}")
    print(f"  {total_graphs} graphs · {total_triples} triples · {len(node_info)} nodes · {len(seen_eids)} edges")


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
