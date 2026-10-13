/**
 * @file
 * Knowledge graph explorer: schema view and neighbourhood view, drawn with d3.
 *
 * All data shown comes from the triplestore and is treated as untrusted: it
 * is only ever written with textContent / SVG text, never as HTML.
 *
 * The side panel carries the same information as the drawing, as text and
 * buttons, so the page is usable without the SVG (keyboard, screen reader).
 */
((Drupal, drupalSettings, once, d3) => {
  'use strict';

  const t = Drupal.t;

  /** New nodes added per expansion; the panel can add any specific relation. */
  const NEIGHBOUR_CAP = 40;
  /** Rows shown per predicate in the panel before "Show all". */
  const PANEL_ROWS = 20;
  /** Draw edge labels only while the drawing stays readable. */
  const EDGE_LABEL_MAX = 80;
  /** Above this many active links, their labels would overlap: show none. */
  const ACTIVE_LABEL_MAX = 12;
  /** Above this many nodes, leaf labels show only around the focus. */
  const BUSY_NODES = 25;
  const IRI_HINT = /^[a-z][a-z0-9+.-]*:\S+$/i;

  /**
   * Small DOM builder. Children may be nodes, strings or null.
   */
  function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (value === null || value === undefined || value === false) {
        return;
      }
      if (key === 'class') {
        el.className = value;
      }
      else if (key.startsWith('on') && typeof value === 'function') {
        el.addEventListener(key.slice(2), value);
      }
      else {
        el.setAttribute(key, value === true ? '' : value);
      }
    });
    children.flat().forEach((child) => {
      if (child === null || child === undefined || child === false) {
        return;
      }
      el.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return el;
  }

  function truncate(text, max) {
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
  }

  function formatNumber(n) {
    return new Intl.NumberFormat().format(n);
  }

  class GraphExplorer {
    constructor(root, settings) {
      this.root = root;
      this.settings = settings;
      this.prefixes = settings.prefixes;
      this.groupIndex = new Map(settings.groups.map((g, i) => [g.id, i]));
      this.groupLabel = new Map(settings.groups.map((g) => [g.id, g.label]));
      this.hiddenGroups = new Set();
      this.mode = 'schema';
      this.schema = null;
      this.selected = null;
      this.searchText = '';
      this.resources = new Map();
      this.graph = { nodes: new Map(), links: new Map() };
      this.simulation = null;

      this.els = {
        panel: root.querySelector('[data-sge-panel]'),
        svg: d3.select(root.querySelector('[data-sge-canvas]')),
        legend: root.querySelector('[data-sge-legend]'),
        status: root.querySelector('[data-sge-status]'),
        alerts: root.querySelector('[data-sge-alerts]'),
        hint: root.querySelector('[data-sge-hint]'),
        search: root.querySelector('[data-sge-search] input'),
        modeButtons: root.querySelectorAll('[data-sge-mode]'),
        clear: root.querySelector('[data-sge-action="clear"]'),
      };

      this.setUpCanvas();
      this.bindToolbar();
      this.route();
      window.addEventListener('hashchange', () => this.route());
    }

    /* ------------------------------------------------------------------ */
    /* Names and colours                                                    */
    /* ------------------------------------------------------------------ */

    prefixFor(iri) {
      return this.prefixes.find((p) => iri.startsWith(p.ns)) || null;
    }

    compact(iri) {
      if (iri === this.settings.untyped) {
        return t('(untyped)');
      }
      const p = this.prefixFor(iri);
      if (p) {
        return `${p.prefix}:${iri.slice(p.ns.length)}`;
      }
      return iri;
    }

    groupOf(iri) {
      if (iri === this.settings.untyped) {
        return 'other';
      }
      const p = this.prefixFor(iri);
      return p ? p.group : 'other';
    }

    colour(group) {
      return `var(--sge-group-${this.groupIndex.get(group) ?? this.groupIndex.get('other')})`;
    }

    swatch(group) {
      const el = h('span', { class: 'sge__swatch', 'aria-hidden': 'true' });
      el.style.background = this.colour(group);
      return el;
    }

    /* ------------------------------------------------------------------ */
    /* Data access                                                          */
    /* ------------------------------------------------------------------ */

    async api(name, params = {}) {
      const url = new URL(this.settings.api[name], window.location.origin);
      Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      let body = {};
      try {
        body = await response.json();
      }
      catch (e) {
        // Keep the empty body; the status code below explains the failure.
      }
      if (!response.ok) {
        throw new Error(body.error || t('Request failed (HTTP @status).', { '@status': response.status }));
      }
      return body;
    }

    async resource(iri) {
      if (!this.resources.has(iri)) {
        this.resources.set(iri, await this.api('resource', { iri }));
      }
      return this.resources.get(iri);
    }

    /**
     * Runs an async action with status text; failures become an alert.
     */
    async run(statusText, action) {
      this.status(statusText);
      try {
        return await action();
      }
      catch (error) {
        this.alert('danger', error.message);
        return null;
      }
      finally {
        this.status('');
      }
    }

    status(text) {
      this.els.status.textContent = text;
    }

    alert(kind, text) {
      const close = h('button', {
        type: 'button',
        class: 'btn-close',
        'aria-label': t('Close'),
        onclick: () => box.remove(),
      });
      const box = h('div', { class: `alert alert-${kind} d-flex justify-content-between align-items-start gap-2`, role: kind === 'danger' ? 'alert' : 'status' }, h('div', {}, text), close);
      this.els.alerts.append(box);
    }

    /* ------------------------------------------------------------------ */
    /* Routing (URL hash keeps a view shareable and survives reloads)       */
    /* ------------------------------------------------------------------ */

    route() {
      const hash = decodeURIComponent(window.location.hash.slice(1));
      const [key, ...rest] = hash.split('=');
      const value = rest.join('=');
      if (key === 'explore' && value) {
        if (this.mode !== 'explore' || !this.graph.nodes.has(value)) {
          this.explore(value, { reset: this.mode !== 'explore' });
        }
        else {
          this.select(value);
        }
        return;
      }
      this.showSchema({ select: key === 'class' && value ? value : null });
    }

    setHash(key, value) {
      const next = value ? `#${key}=${encodeURIComponent(value)}` : `#${key}`;
      if (window.location.hash !== next) {
        window.history.replaceState(null, '', next);
      }
    }

    /* ------------------------------------------------------------------ */
    /* Toolbar                                                              */
    /* ------------------------------------------------------------------ */

    bindToolbar() {
      this.els.modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
          if (button.dataset.sgeMode === 'schema') {
            this.showSchema({ select: this.selectedClass || null });
          }
          else if (this.graph.nodes.size) {
            this.setMode('explore');
            this.draw();
            this.select(this.selected && this.graph.nodes.has(this.selected) ? this.selected : [...this.graph.nodes.keys()][0]);
          }
          else {
            this.setMode('explore');
            this.draw();
            this.panelHelpExplore();
          }
        });
      });

      this.els.search.addEventListener('input', () => {
        this.searchText = this.els.search.value.trim().toLowerCase();
        this.applyDimming();
      });
      this.els.search.form.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = this.els.search.value.trim();
        if (IRI_HINT.test(value) && value.includes('/')) {
          this.els.search.value = '';
          this.searchText = '';
          this.explore(value, { reset: true });
        }
      });

      this.root.querySelector('[data-sge-action="refresh"]').addEventListener('click', () => {
        this.showSchema({ refresh: true, select: this.selectedClass || null });
      });
      this.root.querySelector('[data-sge-action="fit"]').addEventListener('click', () => this.fit());
      this.els.clear.addEventListener('click', () => {
        this.graph = { nodes: new Map(), links: new Map() };
        this.selected = null;
        this.draw();
        this.panelHelpExplore();
      });
    }

    setMode(mode) {
      this.mode = mode;
      this.els.modeButtons.forEach((button) => {
        const active = button.dataset.sgeMode === mode;
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-outline-primary', !active);
        button.setAttribute('aria-pressed', String(active));
      });
      this.els.clear.hidden = mode !== 'explore';
      this.els.search.placeholder = mode === 'schema'
        ? t('Filter classes, or paste an IRI')
        : t('Highlight nodes, or paste an IRI');
      this.els.hint.textContent = mode === 'schema'
        ? t('Click a class for details. Scroll to zoom, drag to pan.')
        : t('Click a node for details, double-click to expand it.');
    }

    /* ------------------------------------------------------------------ */
    /* Schema view                                                          */
    /* ------------------------------------------------------------------ */

    async showSchema({ refresh = false, select = null } = {}) {
      this.setMode('schema');
      if (!this.schema || refresh) {
        const schema = await this.run(refresh ? t('Recomputing schema from the triplestore…') : t('Loading schema…'), () => this.api('schema', refresh ? { refresh: 1 } : {}));
        if (!schema) {
          return;
        }
        this.schema = schema;
        if (schema.partial) {
          this.alert('warning', t('The schema is incomplete: the triplestore stopped a query at its time limit, so counts are too low and some classes or links may be missing. It was not cached; refresh to try again. (@reason)', { '@reason': schema.partialReason }));
        }
      }
      this.buildSchemaGraph();
      this.draw();
      if (select && this.schemaNodes.has(select)) {
        this.selectClass(select);
      }
      else {
        this.selectedClass = null;
        this.setHash('schema');
        this.panelSchemaOverview();
      }
    }

    buildSchemaGraph() {
      const nodes = new Map();
      this.schema.classes.forEach((c) => {
        nodes.set(c.iri, { id: c.iri, count: c.count, group: this.groupOf(c.iri) });
      });
      const links = new Map();
      this.schema.links.forEach((l) => {
        [l.source, l.target].forEach((iri) => {
          if (!nodes.has(iri)) {
            nodes.set(iri, { id: iri, count: 0, group: this.groupOf(iri), external: true });
          }
        });
        const key = `${l.source}\n${l.target}`;
        if (!links.has(key)) {
          links.set(key, { key, source: l.source, target: l.target, count: 0, predicates: [] });
        }
        const link = links.get(key);
        link.count += l.count;
        link.predicates.push({ predicate: l.predicate, count: l.count });
      });
      links.forEach((link) => link.predicates.sort((a, b) => b.count - a.count));

      // Keep positions when the schema is redrawn (refresh, mode switch).
      const previous = this.schemaNodes || new Map();
      nodes.forEach((node, id) => {
        const old = previous.get(id);
        if (old) {
          Object.assign(node, { x: old.x, y: old.y });
        }
      });
      this.schemaNodes = nodes;
      this.schemaLinks = links;
    }

    selectClass(iri) {
      this.selectedClass = iri;
      this.selected = iri;
      this.setHash('class', iri);
      this.markSelected();
      this.panelClass(iri);
    }

    panelSchemaOverview() {
      const classes = [...this.schemaNodes.values()].filter((n) => !n.external).sort((a, b) => b.count - a.count);
      const generated = new Date(this.schema.generated * 1000);
      this.panel(
        h('h2', {}, t('Schema derived from the data')),
        h('p', { class: 'sge__iri' }, t('Graph @graph, computed @time', { '@graph': this.schema.graph, '@time': generated.toLocaleString() })),
        h('p', {}, t('@classes classes, @links class-to-class links. Circle size is the number of instances; arrows point from the class using a predicate to the class of its values.', {
          '@classes': formatNumber(classes.length),
          '@links': formatNumber(this.schemaLinks.size),
        })),
        h('h3', {}, t('Classes')),
        h('ul', { class: 'sge__rows' }, classes.map((n) => h('li', {},
          h('span', {}, this.swatch(n.group), h('button', { type: 'button', class: 'sge__link', onclick: () => this.selectClass(n.id) }, this.compact(n.id))),
          h('span', { class: 'sge__count' }, formatNumber(n.count)),
        ))),
      );
    }

    panelClass(iri) {
      const node = this.schemaNodes.get(iri);
      const out = this.schema.links.filter((l) => l.source === iri).sort((a, b) => b.count - a.count);
      const inc = this.schema.links.filter((l) => l.target === iri).sort((a, b) => b.count - a.count);
      const literals = this.schema.literals.filter((l) => l.class === iri).sort((a, b) => b.count - a.count);

      const classButton = (target) => h('button', { type: 'button', class: 'sge__link', onclick: () => this.selectClass(target) }, this.compact(target));
      const linkRows = (rows, other) => (rows.length
        ? h('ul', { class: 'sge__rows' }, rows.map((l) => h('li', {},
          h('span', {}, h('span', { class: 'sge__pred' }, this.compact(l.predicate)), ' → ', classButton(l[other])),
          h('span', { class: 'sge__count', title: t('triples') }, formatNumber(l.count)),
        )))
        : h('p', { class: 'text-muted' }, t('None.')));

      this.panel(
        h('button', { type: 'button', class: 'sge__link small mb-2', onclick: () => this.showSchema() }, h('span', { class: 'bi bi-chevron-left', 'aria-hidden': 'true' }), ' ', t('All classes')),
        h('h2', {}, this.swatch(node.group), this.compact(iri)),
        h('p', { class: 'sge__iri' }, iri),
        h('p', {}, node.external
          ? t('Not typed in this graph: values point here, but no subject in the graph has this class.')
          : t('@count instances', { '@count': formatNumber(node.count) })),
        node.external ? null : h('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => this.panelInstances(iri) }, t('Browse instances')),
        h('h3', {}, t('Links out (predicate → class of value)')),
        linkRows(out, 'target'),
        h('h3', {}, t('Links in (class of subject → predicate)')),
        inc.length
          ? h('ul', { class: 'sge__rows' }, inc.map((l) => h('li', {},
            h('span', {}, classButton(l.source), ' ', h('span', { class: 'sge__pred' }, this.compact(l.predicate))),
            h('span', { class: 'sge__count', title: t('triples') }, formatNumber(l.count)),
          )))
          : h('p', { class: 'text-muted' }, t('None.')),
        h('h3', {}, t('Literal fields')),
        literals.length
          ? h('ul', { class: 'sge__rows' }, literals.map((l) => h('li', {},
            h('span', {},
              h('span', { class: 'sge__pred' }, this.compact(l.predicate)),
              h('br'),
              h('small', { class: 'text-muted' }, l.tagged
                ? t('language-tagged in @pct%', { '@pct': Math.round((100 * l.tagged) / Math.max(1, l.count)) })
                : (l.datatype ? this.compact(l.datatype) : t('plain string'))),
            ),
            h('span', { class: 'sge__count', title: t('values') }, formatNumber(l.count)),
          )))
          : h('p', { class: 'text-muted' }, t('None.')),
      );
    }

    async panelInstances(classIri, search = '', offset = 0, list = null) {
      const result = await this.run(t('Loading instances…'), () => this.api('instances', { class: classIri, q: search, offset }));
      if (!result) {
        return;
      }
      const items = result.items.map((item) => h('li', {},
        h('span', {}, h('button', { type: 'button', class: 'sge__link', onclick: () => this.explore(item.iri, { reset: true }) }, item.label || this.compact(item.iri))),
      ));

      if (list) {
        list.querySelector('[data-sge-more]')?.remove();
        list.append(...items);
      }
      else {
        let timer = null;
        const input = h('input', {
          class: 'form-control form-control-sm mb-2',
          type: 'search',
          value: search,
          placeholder: t('Search label or IRI'),
          'aria-label': t('Search instances of @class', { '@class': this.compact(classIri) }),
          oninput: (event) => {
            clearTimeout(timer);
            timer = setTimeout(() => this.panelInstances(classIri, event.target.value, 0), 350);
          },
        });
        list = h('ul', { class: 'sge__rows' }, items);
        this.panel(
          h('button', { type: 'button', class: 'sge__link small mb-2', onclick: () => this.selectClass(classIri) }, h('span', { class: 'bi bi-chevron-left', 'aria-hidden': 'true' }), ' ', this.compact(classIri)),
          h('h2', {}, t('Instances')),
          input,
          items.length ? list : h('p', { class: 'text-muted' }, t('No instances match.')),
        );
        if (search) {
          input.focus();
        }
      }
      if (result.more) {
        list.append(h('li', { 'data-sge-more': true },
          h('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', onclick: () => this.panelInstances(classIri, search, offset + result.items.length, list) }, t('Load more')),
        ));
      }
    }

    /* ------------------------------------------------------------------ */
    /* Explore view                                                         */
    /* ------------------------------------------------------------------ */

    panelHelpExplore() {
      this.panel(
        h('h2', {}, t('Explore')),
        h('p', {}, t('Start from an instance: pick a class in the schema view and browse its instances, or paste an IRI into the search box and press Enter.')),
      );
    }

    async explore(iri, { reset = false } = {}) {
      if (reset) {
        this.graph = { nodes: new Map(), links: new Map() };
        this.fitPending = true;
      }
      this.setMode('explore');
      this.addNode(iri);
      const data = await this.expand(iri);
      if (data) {
        this.select(iri);
      }
    }

    addNode(iri, { label = '', types = [] } = {}) {
      let node = this.graph.nodes.get(iri);
      if (!node) {
        node = { id: iri, label, types, expanded: false };
        this.graph.nodes.set(iri, node);
      }
      if (label && !node.label) {
        node.label = label;
      }
      if (types.length && !node.types.length) {
        node.types = types;
      }
      node.group = this.groupOf(node.types[0] || iri);
      return node;
    }

    addLink(source, target, predicate) {
      const key = `${source}\n${target}`;
      if (!this.graph.links.has(key)) {
        this.graph.links.set(key, { key, source, target, predicates: new Set() });
      }
      this.graph.links.get(key).predicates.add(predicate);
    }

    /**
     * Adds a resource's neighbours to the canvas.
     *
     * With `only`, adds just those entries (one relation from the panel);
     * otherwise up to NEIGHBOUR_CAP new nodes, and says how many were left out.
     */
    async expand(iri, only = null) {
      const data = await this.run(t('Loading @name…', { '@name': this.compact(iri) }), () => this.resource(iri));
      if (!data) {
        return null;
      }
      const node = this.addNode(iri, { label: data.label, types: data.types });
      node.expanded = true;

      const entries = only || [
        ...data.out.map((e) => ({ ...e, direction: 'out' })),
        ...data.in.map((e) => ({ ...e, direction: 'in' })),
      ];
      let added = 0;
      let skipped = 0;
      entries.forEach((entry) => {
        const isNew = !this.graph.nodes.has(entry.iri);
        if (isNew && !only && added >= NEIGHBOUR_CAP) {
          skipped += 1;
          return;
        }
        this.addNode(entry.iri, { label: entry.label, types: entry.types });
        if (entry.direction === 'out') {
          this.addLink(iri, entry.iri, entry.predicate);
        }
        else {
          this.addLink(entry.iri, iri, entry.predicate);
        }
        added += isNew ? 1 : 0;
      });
      const hidden = skipped + Math.max(0, data.inTotal - data.in.length);
      if (hidden > 0 && !only) {
        this.status(t('Added @added neighbours; @hidden more are listed in the panel.', { '@added': added, '@hidden': formatNumber(hidden) }));
        window.setTimeout(() => this.status(''), 6000);
      }
      if (data.partial) {
        this.alert('warning', t('The triplestore truncated the neighbourhood of @name; some links may be missing.', { '@name': this.compact(iri) }));
      }
      this.draw();
      return data;
    }

    removeNode(iri) {
      this.graph.nodes.delete(iri);
      [...this.graph.links.entries()].forEach(([key, link]) => {
        if (link.source === iri || link.target === iri) {
          this.graph.links.delete(key);
        }
      });
      // Drop nodes left with no links, except the ones the user expanded.
      const linked = new Set();
      this.graph.links.forEach((l) => {
        linked.add(l.source);
        linked.add(l.target);
      });
      [...this.graph.nodes.values()].forEach((n) => {
        if (!n.expanded && !linked.has(n.id)) {
          this.graph.nodes.delete(n.id);
        }
      });
      this.selected = null;
      this.draw();
      if (this.graph.nodes.size) {
        this.select([...this.graph.nodes.keys()][0]);
      }
      else {
        this.panelHelpExplore();
      }
    }

    async select(iri) {
      this.selected = iri;
      this.setHash('explore', iri);
      this.markSelected();
      const data = await this.run(t('Loading @name…', { '@name': this.compact(iri) }), () => this.resource(iri));
      if (data && this.selected === iri) {
        this.addNode(iri, { label: data.label, types: data.types });
        this.panelResource(data);
      }
    }

    /**
     * Adds one neighbour (or all of one relation) from the panel, then selects.
     */
    addFromPanel(iri, entries, selectIri = null) {
      entries.forEach((entry) => {
        this.addNode(entry.iri, { label: entry.label, types: entry.types });
        if (entry.direction === 'out') {
          this.addLink(iri, entry.iri, entry.predicate);
        }
        else {
          this.addLink(entry.iri, iri, entry.predicate);
        }
      });
      this.draw();
      if (selectIri) {
        this.select(selectIri);
      }
    }

    panelResource(data) {
      const node = this.graph.nodes.get(data.iri);
      const name = data.label || this.compact(data.iri);

      const copy = h('button', {
        type: 'button',
        class: 'btn btn-sm btn-outline-primary',
        onclick: async () => {
          try {
            await navigator.clipboard.writeText(data.iri);
            this.status(t('IRI copied.'));
          }
          catch (e) {
            this.status(t('Copy failed; select the IRI above instead.'));
          }
          window.setTimeout(() => this.status(''), 3000);
        },
      }, t('Copy IRI'));

      const actions = h('div', { class: 'd-flex flex-wrap gap-2 my-2' },
        node && !node.expanded
          ? h('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => this.expand(data.iri).then(() => this.select(data.iri)) }, t('Expand'))
          : null,
        data.drupal
          ? h('a', { class: 'btn btn-sm btn-outline-primary', href: data.drupal.url, target: '_blank', rel: 'noopener' },
            t('Open in Drupal'), ' ', h('span', { class: 'bi bi-box-arrow-up-right', 'aria-hidden': 'true' }))
          : null,
        copy,
        node ? h('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', onclick: () => this.removeNode(data.iri) }, t('Remove from canvas')) : null,
      );

      const types = data.types.length
        ? h('p', {}, data.types.map((type) => h('button', {
          type: 'button',
          class: 'sge__chip',
          title: t('Show this class in the schema'),
          onclick: () => this.showSchema({ select: type }),
        }, this.swatch(this.groupOf(type)), this.compact(type))))
        : h('p', { class: 'text-muted' }, t('No rdf:type in this graph.'));

      const literals = data.literals.length
        ? h('ul', { class: 'sge__rows' }, data.literals.map((l) => h('li', {},
          h('div', {},
            h('span', { class: 'sge__pred' }, this.compact(l.predicate)),
            l.lang ? h('small', { class: 'text-muted' }, ` @${l.lang}`) : null,
            l.datatype ? h('small', { class: 'text-muted' }, ` ${this.compact(l.datatype)}`) : null,
            h('div', { class: 'sge__literal' }, l.value),
          ),
        )))
        : h('p', { class: 'text-muted' }, t('None.'));

      this.panel(
        h('h2', {}, name),
        h('p', { class: 'sge__iri' }, data.iri),
        types,
        actions,
        data.partial ? h('div', { class: 'alert alert-warning py-1 px-2 small' }, t('Truncated by the triplestore; some links may be missing.')) : null,
        h('h3', {}, t('Literal values')),
        literals,
        h('h3', {}, data.outTruncated ? t('Links out (first @n)', { '@n': data.out.length }) : t('Links out')),
        this.relationList(data.iri, data.out.map((e) => ({ ...e, direction: 'out' }))),
        h('h3', {}, data.inTotal > data.in.length
          ? t('Links in (@shown of @total)', { '@shown': formatNumber(data.in.length), '@total': formatNumber(data.inTotal) })
          : t('Links in')),
        this.relationList(data.iri, data.in.map((e) => ({ ...e, direction: 'in' }))),
      );
    }

    /**
     * Entries grouped by predicate, each with "Add all" and per-item buttons.
     */
    relationList(iri, entries) {
      if (!entries.length) {
        return h('p', { class: 'text-muted' }, t('None.'));
      }
      const byPredicate = d3.group(entries, (e) => e.predicate);
      const groups = [...byPredicate.entries()].sort((a, b) => b[1].length - a[1].length);
      return h('div', {}, groups.map(([predicate, items]) => {
        const rows = items.map((item) => h('li', {},
          h('span', {},
            this.swatch(this.groupOf(item.types[0] || item.iri)),
            h('button', {
              type: 'button',
              class: 'sge__link',
              title: item.iri,
              onclick: () => this.addFromPanel(iri, [item], item.iri),
            }, item.label || this.compact(item.iri)),
          ),
        ));
        const list = h('ul', { class: 'sge__rows' }, rows.slice(0, PANEL_ROWS));
        const more = rows.length > PANEL_ROWS
          ? h('button', {
            type: 'button',
            class: 'sge__link small',
            onclick: (event) => {
              list.append(...rows.slice(PANEL_ROWS));
              event.target.remove();
            },
          }, t('Show all @n', { '@n': rows.length }))
          : null;
        return h('div', { class: 'mb-2' },
          h('div', { class: 'd-flex justify-content-between align-items-baseline gap-2 mt-2' },
            h('span', { class: 'sge__pred fw-semibold' }, this.compact(predicate)),
            h('button', { type: 'button', class: 'sge__link small', onclick: () => this.addFromPanel(iri, items) },
              t('Add @n to canvas', { '@n': items.length })),
          ),
          list,
          more,
        );
      }));
    }

    /* ------------------------------------------------------------------ */
    /* Drawing                                                              */
    /* ------------------------------------------------------------------ */

    setUpCanvas() {
      const svg = this.els.svg;
      const defs = svg.append('defs');
      defs.append('marker')
        .attr('id', 'sge-arrow')
        .attr('viewBox', '0 -5 10 10')
        .attr('refX', 9)
        .attr('refY', 0)
        // Fixed size: by default markers scale with stroke width, and the
        // widest links would get arrowheads larger than the nodes.
        .attr('markerUnits', 'userSpaceOnUse')
        .attr('markerWidth', 9)
        .attr('markerHeight', 9)
        .attr('orient', 'auto')
        .append('path')
        .attr('class', 'sge-arrow')
        .attr('d', 'M0,-5L10,0L0,5');

      this.viewport = svg.append('g');
      this.layers = {
        links: this.viewport.append('g'),
        labels: this.viewport.append('g'),
        nodes: this.viewport.append('g'),
      };
      this.zoom = d3.zoom()
        .scaleExtent([0.05, 4])
        .on('zoom', (event) => this.viewport.attr('transform', event.transform));
      svg.call(this.zoom).on('dblclick.zoom', null);
      this.fitPending = true;
    }

    size() {
      const box = this.els.svg.node().getBoundingClientRect();
      return { width: box.width || 800, height: box.height || 600 };
    }

    /**
     * Current nodes and links for the mode, minus hidden namespace groups.
     */
    currentData() {
      let nodes;
      let links;
      if (this.mode === 'schema') {
        nodes = [...(this.schemaNodes || new Map()).values()];
        links = [...(this.schemaLinks || new Map()).values()].map((l) => ({ ...l }));
      }
      else {
        nodes = [...this.graph.nodes.values()];
        links = [...this.graph.links.values()].map((l) => ({
          key: l.key,
          source: l.source,
          target: l.target,
          predicates: [...l.predicates].map((p) => ({ predicate: p })),
          count: l.predicates.size,
        }));
      }
      const visible = new Set(nodes.filter((n) => !this.hiddenGroups.has(n.group)).map((n) => n.id));
      return {
        nodes: nodes.filter((n) => visible.has(n.id)),
        links: links.filter((l) => visible.has(l.source) && visible.has(l.target)),
        allGroups: new Set(nodes.map((n) => n.group)),
      };
    }

    draw() {
      const { nodes, links, allGroups } = this.currentData();
      const schema = this.mode === 'schema';
      const { width, height } = this.size();

      const maxCount = d3.max(nodes, (n) => n.count || 0) || 1;
      const radius = schema
        ? d3.scaleSqrt().domain([0, maxCount]).range([5, 34])
        : null;
      const r = (n) => (schema ? (n.external ? 7 : radius(n.count)) : (n.expanded ? 10 : 7));
      nodes.forEach((n) => { n.r = r(n); });

      const maxLink = d3.max(links, (l) => l.count) || 1;
      const width1 = d3.scaleLog().domain([1, Math.max(2, maxLink)]).range([1, schema ? 7 : 2.5]).clamp(true);
      const pairs = new Set(links.map((l) => `${l.source}\n${l.target}`));
      links.forEach((l) => {
        l.bidirectional = l.source !== l.target && pairs.has(`${l.target}\n${l.source}`);
      });

      // Links.
      const linkSel = this.layers.links.selectAll('path').data(links, (l) => l.key)
        .join('path')
        .attr('class', 'sge-link')
        .attr('marker-end', 'url(#sge-arrow)')
        .attr('stroke-width', (l) => width1(Math.max(1, l.count)));
      linkSel.selectAll('title').data((l) => [l]).join('title')
        .text((l) => `${this.compact(typeof l.source === 'object' ? l.source.id : l.source)} → ${this.compact(typeof l.target === 'object' ? l.target.id : l.target)}\n${l.predicates.map((p) => `${this.compact(p.predicate)}${p.count ? ` (${formatNumber(p.count)})` : ''}`).join('\n')}`);

      // Edge labels exist for every link but show only for the links of the
      // hovered or selected node (.is-active); all at once they are unreadable.
      const showEdgeLabels = links.length <= EDGE_LABEL_MAX || !schema;
      const labelSel = this.layers.labels.selectAll('text').data(showEdgeLabels ? links : [], (l) => l.key)
        .join('text')
        .attr('class', 'sge-edge-label')
        .attr('text-anchor', 'middle')
        .text((l) => truncate(this.compact(l.predicates[0].predicate), 30) + (l.predicates.length > 1 ? ` +${l.predicates.length - 1}` : ''));

      // Nodes.
      const nodeSel = this.layers.nodes.selectAll('g.sge-node').data(nodes, (n) => n.id)
        .join((enter) => {
          const g = enter.append('g').attr('class', 'sge-node').attr('tabindex', 0).attr('role', 'button');
          g.append('circle');
          g.append('text').attr('text-anchor', 'middle');
          g.append('title');
          return g;
        });
      nodeSel
        .classed('is-leaf', (n) => !schema && !n.expanded)
        .attr('aria-label', (n) => this.nodeName(n));
      nodeSel.select('circle')
        .attr('r', (n) => n.r)
        .style('fill', (n) => this.colour(n.group));
      nodeSel.select('text')
        .attr('dy', (n) => n.r + 12)
        .text((n) => truncate(this.nodeName(n), 32) + (schema && !n.external ? ` (${formatNumber(n.count)})` : ''));
      nodeSel.select('title').text((n) => `${this.nodeName(n)}\n${n.id}`);

      nodeSel
        .on('click', (event, n) => this.onNodeClick(n))
        .on('dblclick', (event, n) => {
          event.preventDefault();
          if (!schema) {
            this.expand(n.id).then(() => this.select(n.id));
          }
        })
        .on('keydown', (event, n) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.onNodeClick(n);
          }
        })
        .on('mouseenter focus', (event, n) => this.highlight(n.id))
        .on('mouseleave blur', () => this.highlight(null))
        .call(d3.drag()
          .on('start', (event, n) => {
            if (!event.active) {
              this.simulation.alphaTarget(0.2).restart();
            }
            n.fx = n.x;
            n.fy = n.y;
          })
          .on('drag', (event, n) => {
            n.fx = event.x;
            n.fy = event.y;
          })
          .on('end', (event, n) => {
            if (!event.active) {
              this.simulation.alphaTarget(0);
            }
            n.fx = null;
            n.fy = null;
          }));

      this.linkSel = linkSel;
      this.nodeSel = nodeSel;
      this.labelSel = labelSel;

      // Layout.
      nodes.forEach((n) => {
        if (n.x === undefined) {
          n.x = width / 2 + (Math.random() - 0.5) * 100;
          n.y = height / 2 + (Math.random() - 0.5) * 100;
        }
      });
      if (this.simulation) {
        this.simulation.stop();
      }
      const linkForce = d3.forceLink(links).id((n) => n.id)
        .distance((l) => (schema ? 130 : 70) + (l.source.r || 7) + (l.target.r || 7))
        .strength(schema ? 0.15 : 0.5);
      this.simulation = d3.forceSimulation(nodes)
        .force('link', linkForce)
        .force('charge', d3.forceManyBody().strength(schema ? -1100 : -320))
        .force('collide', d3.forceCollide().radius((n) => n.r + (schema ? 30 : 16)))
        .force('x', d3.forceX(width / 2).strength(0.04))
        .force('y', d3.forceY(height / 2).strength(0.06))
        .on('tick', () => this.tick());
      this.fitPending = this.fitPending || schema;
      // The schema is small and redrawn whole: lay it out before showing it,
      // so it appears settled and fitted instead of drifting for seconds.
      // Explore stays animated so newly added neighbours visibly arrive.
      if (schema || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        this.simulation.stop();
        for (let i = 0; i < 300; i += 1) {
          this.simulation.tick();
        }
        this.tick();
        if (this.fitPending) {
          this.fit();
          this.fitPending = false;
        }
      }
      this.simulation.on('end', () => {
        if (this.fitPending) {
          this.fit();
          this.fitPending = false;
        }
      });

      this.root.classList.toggle('is-busy', !schema && nodes.length > BUSY_NODES);
      this.drawLegend(allGroups);
      this.markSelected();
      this.applyDimming();
    }

    nodeName(n) {
      if (this.mode === 'schema') {
        return this.compact(n.id);
      }
      return n.label || this.compact(n.id);
    }

    onNodeClick(n) {
      if (this.mode === 'schema') {
        this.selectClass(n.id);
      }
      else {
        this.select(n.id);
      }
    }

    /**
     * Path from source to target, stopping at the target's edge so the arrow
     * shows. Reciprocal pairs curve apart; self-links loop above the node.
     */
    linkPath(l) {
      const s = l.source;
      const tg = l.target;
      if (s === tg) {
        const loop = s.r + 16;
        return `M${s.x - s.r * 0.6},${s.y - s.r * 0.8} A${loop * 0.6},${loop * 0.6} 0 1,1 ${s.x + s.r * 0.6},${s.y - s.r * 0.8}`;
      }
      const dx = tg.x - s.x;
      const dy = tg.y - s.y;
      const dist = Math.hypot(dx, dy) || 1;
      const bend = l.bidirectional ? 0.18 : 0;
      const cx = (s.x + tg.x) / 2 - dy * bend;
      const cy = (s.y + tg.y) / 2 + dx * bend;
      const ex = tg.x - cx;
      const ey = tg.y - cy;
      const elen = Math.hypot(ex, ey) || 1;
      const endX = tg.x - (ex / elen) * (tg.r + 2);
      const endY = tg.y - (ey / elen) * (tg.r + 2);
      l.mid = [0.25 * s.x + 0.5 * cx + 0.25 * endX, 0.25 * s.y + 0.5 * cy + 0.25 * endY];
      return bend ? `M${s.x},${s.y} Q${cx},${cy} ${endX},${endY}` : `M${s.x},${s.y} L${endX},${endY}`;
    }

    tick() {
      this.linkSel.attr('d', (l) => this.linkPath(l));
      this.labelSel
        .attr('x', (l) => (l.mid ? l.mid[0] : l.source.x))
        .attr('y', (l) => (l.mid ? l.mid[1] : l.source.y - l.source.r - 20));
      this.nodeSel.attr('transform', (n) => `translate(${n.x},${n.y})`);
    }

    fit() {
      const bounds = this.viewport.node().getBBox();
      if (!bounds.width || !bounds.height) {
        return;
      }
      const { width, height } = this.size();
      const scale = Math.min(1.25, 0.9 / Math.max(bounds.width / width, bounds.height / height));
      const transform = d3.zoomIdentity
        .translate(width / 2, height / 2)
        .scale(scale)
        .translate(-(bounds.x + bounds.width / 2), -(bounds.y + bounds.height / 2));
      this.els.svg.transition().duration(400).call(this.zoom.transform, transform);
    }

    drawLegend(groups) {
      const present = this.settings.groups.filter((g) => groups.has(g.id));
      this.els.legend.replaceChildren(...present.map((g) => h('li', {},
        h('button', {
          type: 'button',
          'aria-pressed': String(!this.hiddenGroups.has(g.id)),
          onclick: () => {
            if (this.hiddenGroups.has(g.id)) {
              this.hiddenGroups.delete(g.id);
            }
            else {
              this.hiddenGroups.add(g.id);
            }
            this.draw();
          },
        }, this.swatch(g.id), g.label),
      )));
      this.els.legend.hidden = present.length === 0;
    }

    markSelected() {
      if (this.nodeSel) {
        this.nodeSel.classed('is-selected', (n) => n.id === this.selected);
        this.markActive(this.selected);
      }
    }

    /**
     * Marks the links (and their labels) touching a node as active.
     */
    markActive(id) {
      const touches = (l) => id !== null && (l.source.id === id || l.target.id === id);
      let active = 0;
      const near = new Set();
      this.linkSel.classed('is-active', (l) => {
        const on = touches(l);
        if (on) {
          active += 1;
          near.add(l.source.id);
          near.add(l.target.id);
        }
        return on;
      });
      this.labelSel.classed('is-active', (l) => active <= ACTIVE_LABEL_MAX && touches(l));
      this.nodeSel.classed('is-near', (n) => near.has(n.id));
    }

    /**
     * Dims nodes not matching the search box, and links between dimmed nodes.
     */
    applyDimming() {
      if (!this.nodeSel) {
        return;
      }
      const q = this.searchText;
      const match = (n) => !q || n.id.toLowerCase().includes(q) || this.nodeName(n).toLowerCase().includes(q);
      this.nodeSel.classed('is-dimmed', (n) => !match(n));
      this.linkSel.classed('is-dimmed', (l) => q && !(match(l.source) && match(l.target)));
      this.labelSel.classed('is-dimmed', (l) => q && !(match(l.source) && match(l.target)));
    }

    /**
     * On hover or focus, dims everything not adjacent to the node.
     */
    highlight(id) {
      if (!id) {
        this.markActive(this.selected);
        this.applyDimming();
        return;
      }
      const near = new Set([id]);
      this.linkSel.each((l) => {
        if (l.source.id === id) {
          near.add(l.target.id);
        }
        if (l.target.id === id) {
          near.add(l.source.id);
        }
      });
      this.nodeSel.classed('is-dimmed', (n) => !near.has(n.id));
      this.markActive(id);
      this.linkSel.classed('is-dimmed', (l) => l.source.id !== id && l.target.id !== id);
      this.labelSel.classed('is-dimmed', (l) => l.source.id !== id && l.target.id !== id);
    }

    panel(...children) {
      this.els.panel.replaceChildren(...children.filter(Boolean));
      this.els.panel.scrollTop = 0;
    }
  }

  Drupal.behaviors.stiGraphExplorer = {
    attach(context) {
      once('sti-graph-explorer', '[data-sti-graph-explorer]', context).forEach((root) => {
        root.stiGraphExplorer = new GraphExplorer(root, drupalSettings.stiGraphExplorer);
      });
    },
  };
})(Drupal, drupalSettings, once, d3);
