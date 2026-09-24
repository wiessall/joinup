# FAO Design System theme (lean)

A standalone Drupal theme (`base theme: false`) that turns Drupal output into
FAO Design System markup. It has 29 files plus 13 font files (the previous version had 341 files).

```
Bootstrap 5.3.8  (vendor/bootstrap)     grid, forms, buttons, dropdowns
  └─ FAO DS 3.6.5 (vendor/fao-ds)       FAO look: colours, type, header, footer, cards
       └─ this theme                    makes Drupal print the HTML those two expect
```

## How it is organised

| Where | What | Rule |
|---|---|---|
| `vendor/` | Bootstrap + FAO DS, minified, unmodified except FAO DS's remote `@import`s removed | Never edit; replace on upgrade (see below) |
| `css/base/` | Self-hosted Open Sans, a 2.5 KB icon subset, component tokens | Global, tiny |
| `css/layout/` | Layout Builder region fix, `.fao-card-grid`, sidebar grid | Global, tiny |
| `fao.theme` | Preprocess: adds Bootstrap classes to **all** inputs, selects, textareas, labels, tabs; card data; FAO header/footer data | One function per element *type*, never per element ID |
| `templates/` | 10 generic templates: page, breadcrumb, main menu, tabs, pager, messages, tile, search listing | Only where markup (not just classes) must differ from core |
| `components/` | SDCs in FAO DS markup: `card`, `fao-header`, `fao-footer` | One folder per Design System pattern; CSS/JS inside the folder loads only when used |
| `data/fao-chrome.json` | FAO-wide header/footer links and logos, 6 languages | Corporate data, not edited per site |
| `scripts/build-icons.py` | Regenerates the icon subset | Run when you need a new icon |

Global download: about 52 KB CSS + 24 KB JS (gzipped), no jQuery, and no CSS, JS or fonts from third-party servers (the FAO logos still load from fao.org, see section 6).
Before: about 51 KB CSS + 67 KB JS, jQuery, and a 134 KB icon font plus CSS from jsDelivr.

## What was removed, and why

| Removed | Count | Reason |
|---|---|---|
| Bootstrap 5.0.2 distribution | 44 files, 7.1 MB | 2 files were used; wrong version (FAO DS needs 5.3) |
| Source maps | 24 files, 5.2 MB | Debug only |
| Unused or duplicate CSS (other FAO sites, older builds) | 8 | Never loaded |
| Per-element form templates | 61 | Replaced by 6 preprocess functions |
| STI view templates | 29 | Tied to views that don't exist in Joinup |
| STI content/field/taxonomy templates | ~55 | `innovation`, `digital_asset`… don't exist yet; rebuild on components when they do |
| Block templates keyed to STI block IDs | 19 | Block IDs differ in Joinup |
| `fao.layouts.yml` | 6 layouts | 3 pointed at templates that don't exist; Joinup ships its own layouts |
| `components/` card variants, hero, counter… | 9 | Unused in Joinup; old versions had `\|raw` and a Twig Tweak dependency. Port as needed (see below) |
| `templates/components/`, `content/` | 2 dirs | Duplicate, undiscovered copies |
| `html.html.twig` | 1 | It hard-coded Google Tag Manager `GTM-NHPTQQS`. Tracking belongs in the `google_tag` module's config, not the theme |
| JS: Swiper, throbber, expandable, Chosen facets, fao.js | 5 | Page-specific or replaced by CSS/core; jQuery no longer needed |
| `scss/`, `includes/*.inc`, 5 of 7 JSON files, `.bak/.old/.DS_Store` | many | Unused or superseded |

Keep a copy of the old theme folder (or the archive you uploaded) to take pieces back from; it is not in the Joinup repository.

## Install

```bash
ddev drush theme:install 'fao' -y        # already installed: just replace the folder
ddev drush config:set 'system.theme' 'default' 'fao' -y
ddev drush cr
```

Keep `ventuno` **installed** (not default). `mailsystem.settings` renders
Joinup's emails with `ventuno`, and uninstalling it would break mail templates.

## Elements to think about, and how

### 1. Block placement (do this first)
Joinup builds much of each page from blocks placed for `ventuno`: page title, tabs,
local actions, group header, group menu, "Add content", content-type facet,
flags, messages, search box. None of them exist for `fao` until you place
them. Map `ventuno` regions to `fao` regions:

| ventuno | fao |
|---|---|
| header (group header, group menu) | header |
| featured (three-dots, Add content) | header or content (top) |
| content_top (like/bookmark) | content |
| messages | messages |
| content (title, tabs, facets, main content) | content |
| sidebar | sidebar |
| footer | footer (appears above the corporate footer) |
| top_bar (language picker) | header, or the `tools` slot of `fao-header` |

Do it once in Block layout and export with `cex`, or better, as a `fao_base`
recipe using `placeBlockInDefaultTheme`, so it can be reproduced. After placing the
Tabs block, set its machine name under `joinup:` in `fao.info.yml`.

### 2. Listings and view modes
- `view_mode_tile` renders the `fao:card` component. Choose image and text
  per bundle in **Manage display → Tile**; the preprocess only reads what the
  display renders. Use a trimmed formatter for long text.
- Other listing view modes (`view_mode_tile_horizontal`, `view_mode_search_list_item`,
  `carousel`, `teaser`) fall back to core's plain node template. Decide per
  mode: map it to `fao:card` (copy the tile template), or build a new
  component (e.g. `card-horizontal`).
- Views listings: add `fao-card-grid` as the wrapper class under Format → Settings.

### 3. Group pages (collections, solutions)
The group header, group menu and Join/Leave buttons had their look in `ventuno`
templates. They now render with module defaults (functional, unstyled).
Build one `group-header` component in FAO DS markup and map the
`group_header` view mode to it, following the same pattern as the card.

### 4. Search and facets
- `search_api_field` results sit in `.fao-card-grid` (2 columns next to filters, more at full width).
- Facet widgets use the Facets module's markup; add Bootstrap classes in a
  `fao_preprocess_facets_*` function, or a single template if structure must change.
  Use the hierarchical checkbox widget plus `<details>` for trees, not Chosen.
- Loading feedback: enable AJAX on facets and style core's `.ajax-progress`
  in CSS, rather than restoring `throbber.js`.

### 5. Forms
Every `<input>`, `<select>`, `<textarea>` and label gets Bootstrap classes from
preprocess. Check the complex widgets: entity autocomplete, file upload,
date, inline entity forms, and Joinup's own. Add validation styling (`is-invalid`
on elements with errors) in `fao_preprocess_input()` if the FAO DS error look is needed.
Floating labels were dropped: they need label-after-input markup for every element.

### 6. Header and footer
- Corporate links and logos: `data/fao-chrome.json`. Logos and social icons
  are still hot-linked from fao.org. Copy them into `images/` and change the
  paths if you want no third-party requests.
- Site name comes from Basic site settings. The main menu goes in Primary
  navigation. The language switcher block goes in Header (or build a small
  component for the `fao-header` `tools` slot).
- Next step (optional): a `fao_chrome` module with two block plugins, so header and footer
  are placeable and cacheable blocks instead of being hard-wired in `page.html.twig`.

### 7. Icons
FAO DS CSS uses the Bootstrap Icons font for chevrons, close buttons and similar.
`fonts/bootstrap-icons-subset.woff2` holds only those glyphs plus the ones
listed in `scripts/build-icons.py`. To add an icon, add its name there and
rerun the script. Use it as `<span class="bi bi-NAME" aria-hidden="true"></span>`.

### 8. Components to port when a page needs them
Hero banner, counter, card-horizontal, card-overlay, block title. For each one:
1. Copy the FAO DS markup from design-system.fao.org or the old theme into
   `components/NAME/NAME.twig`.
2. Declare props (plain values) and slots (rendered Drupal output) in
   `NAME.component.yml`. Never use `|raw`.
3. Put only CSS the DS lacks in `NAME.css`, and JS (e.g. counter animation) in
   `NAME.js`. Both load only where the component renders.
4. Map to it from a thin template (`paragraph--hero-banner.html.twig`,
   `block--block-content--TYPE.html.twig`, `node--VIEWMODE.html.twig`).
5. Turn it on with a recipe (view mode, display fields, block placement).

### 9. Layout Builder
Core layouts and Joinup's `joinup_layout` layouts ship their own templates and
work under this theme. `css/layout/layout.css` fixes flex-region overflow (long
selects widening a column). Check the Layout Builder editing UI once; it
uses this front-end theme.

### 10. Upgrading the vendor files

```bash
npm pack fao-design-system@<version> bootstrap@<version>
# FAO DS: copy fao-theme.min.css, then remove its @import lines (Google Fonts,
# jsDelivr icons). Fonts are self-hosted; icons come from the subset.
# Bootstrap: copy dist/css/bootstrap.min.css and dist/js/bootstrap.bundle.min.js.
```

Update `version:` in `fao.libraries.yml` so browsers fetch the new files. The npm
registry has FAO DS up to 3.7.2 at the time of writing; test before upgrading.
Later, if the global CSS needs to shrink, compile only the Bootstrap parts you use with Sass.

### 11. Accessibility
The skip link comes from core's `html.html.twig` and targets `#main-content`.
The main menu home link has hidden text. Each card is one link. Check heading
order on real pages: the site name is not an `<h1>`; the page title block provides it.
