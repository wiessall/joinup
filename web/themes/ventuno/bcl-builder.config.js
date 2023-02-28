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
      entry: path.resolve(outputFolder, "src/js/content-tables.js"),
      dest: path.resolve(outputFolder, "assets/js/content-tables.js"),
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
      entry: path.resolve(outputFolder, "src/js/toggle-text-seemore-btn.js"),
      dest: path.resolve(outputFolder, "assets/js/toggle-text-seemore-btn.js"),
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
      entry: path.resolve(outputFolder, "src/js/sticky-menu.js"),
      dest: path.resolve(outputFolder, "assets/js/sticky-menu.js"),
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
    // Base
    {
      entry: path.resolve(outputFolder, "src/scss/base.scss"),
      dest: path.resolve(outputFolder, "assets/css/base.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Layout
    {
      entry: path.resolve(outputFolder, "src/scss/layout.scss"),
      dest: path.resolve(outputFolder, "assets/css/layout.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Components
    {
      entry: path.resolve(outputFolder, "src/scss/components.scss"),
      dest: path.resolve(outputFolder, "assets/css/components.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Theme
    {
      entry: path.resolve(outputFolder, "src/scss/theme.scss"),
      dest: path.resolve(outputFolder, "assets/css/theme.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(outputFolder, "src/scss/homepage.scss"),
      dest: path.resolve(outputFolder, "assets/css/homepage.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/licence-comparer.scss"),
      dest: path.resolve(outputFolder, "assets/css/licence-comparer.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/eif-filter.scss"),
      dest: path.resolve(outputFolder, "assets/css/eif-filter.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/licence-filter.scss"),
      dest: path.resolve(outputFolder, "assets/css/licence-filter.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/listing-grid.scss"),
      dest: path.resolve(outputFolder, "assets/css/listing-grid.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/searchpage.scss"),
      dest: path.resolve(outputFolder, "assets/css/searchpage.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Pure BCL components
    {
      entry: path.resolve(outputFolder, "src/scss/accordion.scss"),
      dest: path.resolve(outputFolder, "assets/css/accordion.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/card.scss"),
      dest: path.resolve(outputFolder, "assets/css/card.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/badge.scss"),
      dest: path.resolve(outputFolder, "assets/css/badge.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/close.scss"),
      dest: path.resolve(outputFolder, "assets/css/close.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/modals.scss"),
      dest: path.resolve(outputFolder, "assets/css/modals.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/spinners.scss"),
      dest: path.resolve(outputFolder, "assets/css/spinners.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tables.scss"),
      dest: path.resolve(outputFolder, "assets/css/tables.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Custom and hybrid (BCL + custom) components
    {
      entry: path.resolve(outputFolder, "src/scss/abstract.scss"),
      dest: path.resolve(outputFolder, "assets/css/abstract.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/actions-menu.scss"),
      dest: path.resolve(outputFolder, "assets/css/actions-menu.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/comments.scss"),
      dest: path.resolve(outputFolder, "assets/css/comments.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/fieldset-show-more.scss"),
      dest: path.resolve(outputFolder, "assets/css/fieldset-show-more.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/header-topic.scss"),
      dest: path.resolve(outputFolder, "assets/css/header-topic.min.css"),
      options: {
        includePaths,
        sourceMap: "file",
        minify: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/footer.scss"),
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
        "src/scss/members-overview-exposed-form.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/members-overview-exposed-form.min.css"
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/menu--og--navigation.scss"),
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
      entry: path.resolve(outputFolder, "src/scss/form-checkboxes.scss"),
      dest: path.resolve(outputFolder, "assets/css/form-checkboxes.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/form-checkboxes.scss"),
      dest: path.resolve(outputFolder, "assets/css/form-checkboxes.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/forms.scss"),
      dest: path.resolve(outputFolder, "assets/css/forms.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/glossary.scss"),
      dest: path.resolve(outputFolder, "assets/css/glossary.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/nav.scss"),
      dest: path.resolve(outputFolder, "assets/css/nav.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/ief-entity-table.scss"),
      dest: path.resolve(outputFolder, "assets/css/ief-entity-table.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/iop-header.scss"),
      dest: path.resolve(outputFolder, "assets/css/iop-header.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/layout-block.scss"),
      dest: path.resolve(outputFolder, "assets/css/layout-block.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/layout-block.scss"),
      dest: path.resolve(outputFolder, "assets/css/layout-block.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/navbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/navbar-search.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar-search.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/page-title.scss"),
      dest: path.resolve(outputFolder, "assets/css/page-title.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pager.scss"),
      dest: path.resolve(outputFolder, "assets/css/pager.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/releases.scss"),
      dest: path.resolve(outputFolder, "assets/css/releases.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/share-content-form.scss"),
      dest: path.resolve(outputFolder, "assets/css/share-content-form.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/alert.scss"),
      dest: path.resolve(outputFolder, "assets/css/alert.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tables.scss"),
      dest: path.resolve(outputFolder, "assets/css/tables.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/table-files.scss"),
      dest: path.resolve(outputFolder, "assets/css/table-files.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tabs.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabs.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/toolbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/toolbar.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tour.scss"),
      dest: path.resolve(outputFolder, "assets/css/tour.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tabledrag.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabledrag.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/timeline.scss"),
      dest: path.resolve(outputFolder, "assets/css/timeline.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/user--group-header.scss"),
      dest: path.resolve(outputFolder, "assets/css/user--group-header.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tile-two.scss"),
      dest: path.resolve(outputFolder, "assets/css/tile-two.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tile-two.scss"),
      dest: path.resolve(outputFolder, "assets/css/tile-two.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // @todo ISAICP-7138 remove when switch to Ventuno theme is complete
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/core/base/bootstrap-base.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/bootstrap-base.min.css"),
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
          "calendar3.svg",
          "card-list.svg",
          "chat.svg",
          "chat-left-text.svg",
          "check-circle-fill.svg",
          "chevron-bar-left.svg",
          "chevron-bar-right.svg",
          "chevron-down.svg",
          "chevron-left.svg",
          "chevron-right.svg",
          "chevron-up.svg",
          "clipboard-check.svg",
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
          "filter.svg",
          "folder2-open.svg",
          "gear-fill.svg",
          "github.svg",
          "info-circle-fill.svg",
          "info-circle.svg",
          "journal-text.svg",
          "justify.svg",
          "lightning-charge.svg",
          "link-45deg.svg",
          "linkedin.svg",
          "newspaper.svg",
          "pencil-square.svg",
          "people.svg",
          "person-circle.svg",
          "person-workspace.svg",
          "pin-angle-fill.svg",
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
