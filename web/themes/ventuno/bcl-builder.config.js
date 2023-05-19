/**
 * @file
 * Configures BCL builder to generate assets.
 */
const path = require("path");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [
    // Custom
    {
      entry: path.resolve(outputFolder, "src/js/ajax-throbber.js"),
      dest: path.resolve(outputFolder, "assets/js/ajax-throbber.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/checkbox.js"),
      dest: path.resolve(outputFolder, "assets/js/checkbox.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/fieldset-show-more.js"),
      dest: path.resolve(outputFolder, "assets/js/fieldset-show-more.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/highlight-invalid-fields.js"),
      dest: path.resolve(outputFolder, "assets/js/highlight-invalid-fields.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/responsive-tables.js"),
      dest: path.resolve(outputFolder, "assets/js/responsive-tables.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/searchbox-focus.js"),
      dest: path.resolve(outputFolder, "assets/js/searchbox-focus.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/shared-entity.js"),
      dest: path.resolve(outputFolder, "assets/js/shared-entity.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/js/show-accordion-invalid-fields.js"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/js/show-accordion-invalid-fields.js"
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/sticky-menu-logged-in.js"),
      dest: path.resolve(outputFolder, "assets/js/sticky-menu-logged-in.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/toggle-text-seemore-btn.js"),
      dest: path.resolve(outputFolder, "assets/js/toggle-text-seemore-btn.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/tour-first-balloon.js"),
      dest: path.resolve(outputFolder, "assets/js/tour-first-balloon.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    // Third party
    {
      entry: path.resolve(outputFolder, "src/js/glide.js"),
      dest: path.resolve(outputFolder, "assets/js/glide.js"),
      options: {
        name: "glide",
        minify: true,
        sourceMap: true,
      },
    },
  ],
  styles: [
    // Base (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/01-base.scss"),
      dest: path.resolve(outputFolder, "assets/css/base.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Layout (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/02-layout.scss"),
      dest: path.resolve(outputFolder, "assets/css/layout.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Components (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/03-components.scss"),
      dest: path.resolve(outputFolder, "assets/css/components.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Theme (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/04-theme.scss"),
      dest: path.resolve(outputFolder, "assets/css/theme.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(outputFolder, "src/scss/pages/homepage.scss"),
      dest: path.resolve(outputFolder, "assets/css/homepage.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pages/searchpage.scss"),
      dest: path.resolve(outputFolder, "assets/css/searchpage.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pages/licence-comparer.scss"),
      dest: path.resolve(outputFolder, "assets/css/licence-comparer.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pages/glossary.scss"),
      dest: path.resolve(outputFolder, "assets/css/glossary.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Components
    {
      entry: path.resolve(outputFolder, "src/scss/components/abstract.scss"),
      dest: path.resolve(outputFolder, "assets/css/abstract.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/accordion.scss"),
      dest: path.resolve(outputFolder, "assets/css/accordion.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/actions-menu.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/actions-menu.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/alert.scss"),
      dest: path.resolve(outputFolder, "assets/css/alert.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/badge.scss"),
      dest: path.resolve(outputFolder, "assets/css/badge.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/button-group.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/button-group.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/close.scss"),
      dest: path.resolve(outputFolder, "assets/css/close.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/comments.scss"),
      dest: path.resolve(outputFolder, "assets/css/comments.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/eif-filter.scss"),
      dest: path.resolve(outputFolder, "assets/css/eif-filter.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/etrans.scss"),
      dest: path.resolve(outputFolder, "assets/css/etrans.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/fieldset-show-more.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/fieldset-show-more.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/footer.scss"),
      dest: path.resolve(outputFolder, "assets/css/footer.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/form-checkboxes.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/form-checkboxes.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/forms.scss"),
      dest: path.resolve(outputFolder, "assets/css/forms.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/group-banner.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/group-banner.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/header-topic.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/header-topic.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/ief-entity-table.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/ief-entity-table.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/layout-block.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/layout-block.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/licence-filter.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/licence-filter.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/listing-grid.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/listing-grid.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/menu--og--navigation.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/menu--og--navigation.min.css"
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/modals.scss"),
      dest: path.resolve(outputFolder, "assets/css/modals.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/nav.scss"),
      dest: path.resolve(outputFolder, "assets/css/nav.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/navbar-search.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/navbar-search.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/navbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/navigation-bottom.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/navigation-bottom.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/page-title.scss"),
      dest: path.resolve(outputFolder, "assets/css/page-title.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/pagination.scss"),
      dest: path.resolve(outputFolder, "assets/css/pagination.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/paragraph.scss"),
      dest: path.resolve(outputFolder, "assets/css/paragraph.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/releases.scss"),
      dest: path.resolve(outputFolder, "assets/css/releases.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/share-content-form.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/share-content-form.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/spinners.scss"),
      dest: path.resolve(outputFolder, "assets/css/spinners.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/table-files.scss"),
      dest: path.resolve(outputFolder, "assets/css/table-files.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tabledrag.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabledrag.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tables.scss"),
      dest: path.resolve(outputFolder, "assets/css/tables.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tabs.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabs.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tile-two.scss"),
      dest: path.resolve(outputFolder, "assets/css/tile-two.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tile.scss"),
      dest: path.resolve(outputFolder, "assets/css/tile.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/timeline.scss"),
      dest: path.resolve(outputFolder, "assets/css/timeline.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/toolbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/toolbar.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/tour.scss"),
      dest: path.resolve(outputFolder, "assets/css/tour.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/user--group-header.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/user--group-header.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
  ],
  copy: [
    // Bootstrap: fonts
    {
      from: [
        path.resolve(nodeModules, "@openeuropa/bcl-theme-joinup/fonts/*.css"),
      ],
      to: path.resolve(outputFolder, "assets/css/fonts"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/files/*.woff*"
        ),
      ],
      to: path.resolve(outputFolder, "assets/css/fonts/files"),
      options: { up: true },
    },
    // Bootstrap: scripts
    {
      from: [
        path.resolve(
          nodeModules,
          "@openeuropa/bcl-theme-joinup/js/oe-bcl-joinup.bundle.min.js"
        ),
      ],
      to: path.resolve(outputFolder, "assets/js"),
      options: { up: true },
    },
    // Bootstrap: icons
    {
      from: [
        path.resolve(nodeModules, "@openeuropa/bcl-bootstrap/icons/*.svg"),
      ],
      to: path.resolve(outputFolder, "assets/icons"),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(nodeModules, "@glidejs/glide/dist/css/glide.core.min.css"),
      ],
      to: path.resolve(outputFolder, "assets/css"),
      options: { up: true },
    },
  ],
  sprite: [
    {
      entry: path.resolve(nodeModules, "@openeuropa/bcl-bootstrap/icons/"),
      dest: path.resolve(outputFolder, "assets/icons"),
      options: {
        file: "icons.svg",
        list: [
          "arrow-left.svg",
          "arrow-repeat.svg",
          "arrow-right.svg",
          "award.svg",
          "box-arrow-up-right.svg",
          "calendar.svg",
          "calendar-event.svg",
          "card-list.svg",
          "caret-down-fill.svg",
          "chat.svg",
          "chat-left-text.svg",
          "chat-square.svg",
          "check-circle.svg",
          "check-circle-fill.svg",
          "chevron-bar-left.svg",
          "chevron-bar-right.svg",
          "chevron-down.svg",
          "chevron-left.svg",
          "chevron-right.svg",
          "chevron-up.svg",
          "clipboard2-check.svg",
          "clock-history.svg",
          "download.svg",
          "envelope-fill.svg",
          "exclamation-circle-fill.svg",
          "exclamation-triangle-fill.svg",
          "exclamation-lg.svg",
          "exclamation-triangle-fill.svg",
          "eye.svg",
          "facebook.svg",
          "file-earmark.svg",
          "file-earmark-medical.svg",
          "file-earmark-play.svg",
          "file-earmark-plus.svg",
          "file-earmark-post.svg",
          "file-earmark-richtext.svg",
          "file-earmark-text.svg",
          "file-earmark-zip.svg",
          "file-font.svg",
          "file-x.svg",
          "filter.svg",
          "folder2-open.svg",
          "gear-fill.svg",
          "geo-alt.svg",
          "github.svg",
          "info-circle-fill.svg",
          "info-circle.svg",
          "journal-text.svg",
          "justify.svg",
          "lightning-charge.svg",
          "link-45deg.svg",
          "linkedin.svg",
          "mastodon.svg",
          "newspaper.svg",
          "patch-exclamation.svg",
          "pencil-square.svg",
          "people.svg",
          "person-circle.svg",
          "person-workspace.svg",
          "pin-angle-fill.svg",
          "play-btn.svg",
          "plus.svg",
          "question-lg.svg",
          "reply.svg",
          "search.svg",
          "star.svg",
          "three-dots.svg",
          "trash.svg",
          "type.svg",
          "twitter.svg",
          "vimeo.svg",
          "x-lg.svg",
          "x-square-fill.svg",
          "x.svg",
          "youtube.svg",
        ],
      },
    },
  ],
};
