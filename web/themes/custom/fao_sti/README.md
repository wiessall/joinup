# FAO STI theme (sub-theme of Ventuno)

FAO Design System look on top of Joinup's own theme stack. This is the same
set-up as Joinup's `iop` (Interoperable Europe) theme, a sub-theme of `ventuno`
that swaps the branding layer and keeps everything else.

```
oe_bootstrap_theme   OpenEuropa: Bootstrap 5.3 (BCL) templates, icon sprite
  └─ ventuno         Joinup: tile/pattern preprocessing, search, OG, layouts
       ├─ iop        Interoperable Europe branding  (upstream example)
       └─ fao_sti    FAO branding                   (this theme)
```

## Why a sub-theme, and not the standalone `fao` theme

Joinup's PHP relies on the theme layer more than it seems. For example:

- `_ventuno_preprocess_tile()` builds `image`, `text`, `badges`, `bundle` and
  `extra_data` for every tile. A standalone theme gets none of them.
- `joinup_core`'s `LocalTaskLinksHelper` reads a `joinup:` key from the active
  theme's `.info.yml` to find the tabs block.
- Search, facet, OG navigation and Layout Builder templates all live in ventuno.
- About 30 `block.block.ventuno_*` placements. When you install a sub-theme,
  Drupal copies the default theme's blocks into matching region names.

With `base theme: false` you would have to re-create all of that. As a
sub-theme, you override only what should look different.

## How a page gets its look

1. **Libraries** (`*.libraries.yml`): CSS/JS files. Global ones are listed in
   `.info.yml`. Local ones are attached by a template (`attach_library()`) or
   automatically by an SDC.
2. **Preprocess** (`fao_sti.theme`): PHP that decides *what* to show and
   puts it into template variables. Base-theme preprocess runs first, then the
   sub-theme's.
3. **Templates** (`templates/`): Twig that decides *markup*. Drupal looks
   for a template name in this theme first, then in the base themes. That's
   the entire override mechanism: same file name, closer theme wins.
4. **Components** (`components/*/`): SDC (core since 10.3). Markup, CSS, JS
   and a props schema in one folder, called as `include('fao_sti:card', {...})`.
   Their CSS is attached only on pages where the component renders.

Joinup's `pattern('tile', {...})` is the same idea as (4), using the older
contrib UI Patterns 1.x. UI Patterns 2 is built on SDC, so SDC is the
future-proof choice for new components.

## What this scaffold does

- `templates/patterns/pattern-tile.html.twig` overrides ventuno's tile
  *pattern*. It is not a node template. **Every** tile of every bundle
  (collection pages, search, related solutions, listing paragraphs) now renders
  through `components/card/`. You don't need a per-bundle node template.
- `fao_sti_preprocess_node()` fills in the tile summary everywhere. Ventuno
  only does this on the homepage and meta-entity routes, which is why
  `content.abstract` seemed "missing".
- `css/base/tokens.css` maps FAO colours onto Bootstrap's `--bs-*` variables,
  so ventuno/BCL buttons, links and badges turn FAO blue without template changes.
- `css/vendor/fao-ds.min.css` is the compiled FAO DS without its jsDelivr
  `@import`. Transitional, see "Next steps".

## Header, footer, filters, grid (second round)

- **Header/footer.** `templates/layout/page.html.twig` (a copy of ventuno's)
  puts `fao_sti:fao-header` above Joinup's navbar and replaces ventuno's footer
  with `fao_sti:fao-footer`. The FAO-wide links and logos come from
  `data/fao-chrome.json`, which merges the old `lib/fao-header.json` and
  `fao-footer.json`; the other five JSON files were unused duplicates.
  `fao_sti_preprocess_page()` picks the current language and adds a
  language cache context. Site navigation stays in Drupal menus: the Joinup
  navbar reads the `navbar` menu. Blocks in the Footer region go into the
  footer's `site_links` slot.
- **Filters overlapping results.** `css/layout/layout-builder.css`: flex
  regions default to `min-width: auto`, so a long `<select>` widened the 33%
  column. The fix is `min-width: 0` plus `max-width: 100%` on controls, and a
  sticky, independently scrolling filter column on desktop.
- **Columns.** `card.css` turns any row of FAO cards into a grid keyed to the
  width the row actually has (`auto-fill` + `minmax(17rem, 1fr)`): 2 columns
  next to the filters, 3–4 at full width, with no template overrides. Tune it with
  `--fao-card-min`.
- **Card text.** The tile preprocess now takes the first text field that the
  tile display renders: abstract, then description, then `field_description`,
  then `body`. Choose it under Manage display → *Tile*. Use a trimmed
  formatter there so that 600-word descriptions aren't sent to the browser just
  to be clipped by CSS.

## Install (DDEV)

```bash
# 1. Drop the standalone theme's per-bundle override; the pattern override replaces it.
rm 'web/themes/custom/fao/templates/content/node--solution--view-mode-tile.html.twig'

# 2. Make ventuno the default again so its block placements get copied to fao_sti.
ddev drush config:set 'system.theme' 'default' 'ventuno' -y

# 3. Install and switch.
ddev drush theme:install 'fao_sti' -y
ddev drush config:set 'system.theme' 'default' 'fao_sti' -y
ddev drush cr

# 4. Version-control it.
ddev drush cex -y
```

Keep the standalone `fao` theme folder as a **source** of assets (CSS rules,
header/footer markup, SDCs) to port piece by piece. Uninstall it with
`ddev drush theme:uninstall 'fao'` once nothing references it.

While developing, turn on Twig debug (`ddev drush theme:dev on`). The HTML
source then shows, for every element, which template was used and which file
names would override it.

## Performance: what was left out, and the lighter replacement

| Standalone `fao` theme | Cost | Replacement |
|---|---|---|
| Bootstrap 5.0.2 CSS + JS, on top of BCL's 5.3 | ~180 KB, two Bootstraps whose CSS variables disagree (FAO DS 3.6.5 targets 5.3) | ventuno's BCL build, already loaded |
| `@import` of Bootstrap Icons font from jsDelivr | render-blocking, third-party, chained request | ventuno's SVG sprite (same icon set): `<svg class="bi"><use xlink:href="{{ bcl_icon_path }}#geo-alt-fill"></use></svg>` |
| `{ preprocess: false }` on every file | aggregation off: one request per file | defaults (aggregated) |
| Swiper 160 KB on every page | JS parse on all pages | CSS `scroll-snap` row. Add a few lines of JS for arrow buttons only if needed, and attach it only where used |
| `expandable.js` (swaps text on Bootstrap collapse) | jQuery + BS JS | `<details><summary>`: `details[open] .when-closed { display:none }` |
| `throbber.js` (inline-styled overlay) | JS builds DOM on every submit | use AJAX on the view/facets and style core's `.ajax-progress--fullscreen` in CSS. Ventuno already extends `core/drupal.ajax` |
| `faceted-search-dropdown.js` (Chosen + tree) | jQuery, Chosen, fragile | Facets' hierarchical checkbox widget, nested `<details>` for collapsing |
| `counter.js` count-up animation | small, fine | keep in the counter SDC folder so it only loads with the component; respect `prefers-reduced-motion` |
| Card via `.ratio` padding hack | — | `aspect-ratio: 3/2` + `object-fit: cover` |
| Card clickable area | — | stretched link (`::after { inset:0 }`), one link per card, CSS only |
| 12 Open Sans files with Cyrillic + Latin-ext (~56 KB each) | browsers download only the weights used, but each file is large | later: split by `unicode-range` so Latin pages fetch ~15 KB files |

## Issues in the standalone theme worth knowing about before porting code

- `fao.info.yml`: `core_version_requirement: ^8 || ^9 || ^10` excludes Drupal 11
  (this fork runs 11.3), and the library depends on `sti_leaflet/...`, which
  doesn't exist here.
- SDC `card`:
  - `text|raw` / `link|raw` bypass escaping (XSS risk with harvested content).
  - `drupal_entity()` needs Twig Tweak, which isn't installed.
  - It uses props that aren't in its schema (`image`, `video_url`, `variant_class`).
  - Its CSS lives in the global stylesheet, not with the component.
- SDC `card-small`: unbalanced `<p>` tags.
- `components/` vs `templates/components/` vs `content/`: SDC only discovers the
  top-level `components/` folder. The other copies are dead.
- `includes/node.inc`:
  - SDG classes are keyed on hard-coded term IDs 1158–1174. Those IDs won't
    exist after migration, so key on a term field (e.g. SDG number) instead.
  - The image lookups load media/file entities without adding cache
    dependencies, so a changed image can stay stale in render cache.

## Next steps (suggested order)

1. Check tiles on `/collection/...`. Then compare with the STI card and
   adjust `components/card/card.css`, not the global CSS.
2. Header and footer: port FAO's markup into `templates/layout/page.html.twig`
   (copy ventuno's first, then edit), or into block templates.
3. Hero banner: port `components/hero-banner` as an SDC with a `media` slot
   (see the paragraph-based design from the earlier chat).
4. Shrink `fao-ds.min.css`: for each FAO component you port, move its rules
   into that component's CSS. When the global file only holds typography, turn
   it into `css/base/typography.css` and delete the vendor file.
5. Later, if you want Sass: compile against `@openeuropa/bcl-bootstrap`
   (already in Joinup's `package.json`) with FAO variables, the way `iop`
   does in `src/scss/`, and set `ventuno/components: false` like iop does.
