const path = require("path");
const replace = require("@rollup/plugin-replace");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [
    // Custom
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
      entry: path.resolve(outputFolder, "src/js/searchbox-focus.js"),
      dest: path.resolve(outputFolder, "assets/js/searchbox-focus.js"),
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
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(outputFolder, "src/scss/homepage.scss"),
      dest: path.resolve(outputFolder, "assets/css/homepage.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/licence-comparer.scss"),
      dest: path.resolve(outputFolder, "assets/css/licence-comparer.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/searchpage.scss"),
      dest: path.resolve(outputFolder, "assets/css/searchpage.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Pure BCL components
    {
      entry: path.resolve(outputFolder, "src/scss/accordion.scss"),
      dest: path.resolve(outputFolder, "assets/css/accordion.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/badge.scss"),
      dest: path.resolve(outputFolder, "assets/css/badge.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/close.scss"),
      dest: path.resolve(outputFolder, "assets/css/close.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/modals.scss"),
      dest: path.resolve(outputFolder, "assets/css/modals.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tables.scss"),
      dest: path.resolve(outputFolder, "assets/css/tables.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Custom and hybrid (BCL + custom) components
    {
      entry: path.resolve(outputFolder, "src/scss/footer.scss"),
      dest: path.resolve(outputFolder, "assets/css/footer.css"),
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
      dest: path.resolve(outputFolder, "assets/css/forms.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/ief-entity-table.scss"),
      dest: path.resolve(outputFolder, "assets/css/ief-entity-table.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/navbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/navbar-search.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar-search.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pager.scss"),
      dest: path.resolve(outputFolder, "assets/css/pager.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/site-alerts-messages.scss"),
      dest: path.resolve(outputFolder, "assets/css/site-alerts-messages.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tour.scss"),
      dest: path.resolve(outputFolder, "assets/css/tour.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/tabledrag.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabledrag.css"),
      options: {
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
      dest: path.resolve(outputFolder, "assets/css/bootstrap-base.css"),
      options: {
        includePaths,
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
    // Bootrstrap: scripts
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
    // Theme images
    {
      from: [path.resolve(__dirname, "src/images/favicon/*")],
      to: path.resolve(outputFolder, "assets/images/favicon"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(__dirname, "src/images/homepage-banner/background.svg"),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(__dirname, "src/images/homepage-banner/banner-md.svg"),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/homepage-banner/wave.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/highlighted-event.jpg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/iop-eu-logo.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/logo.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/logo-ec--en.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    // Fontello icons
    {
      from: [path.resolve(__dirname, "src/fonts/joinup-icons/*.woff*")],
      to: path.resolve(outputFolder, "assets/fonts/joinup-icons"),
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
          "arrow-right.svg",
          "award.svg",
          "calendar.svg",
          "calendar3.svg",
          "chat-left-text.svg",
          "check-circle-fill.svg",
          "chevron-bar-left.svg",
          "chevron-bar-right.svg",
          "chevron-left.svg",
          "chevron-right.svg",
          "clipboard-check.svg",
          "exclamation-circle-fill.svg",
          "exclamation-lg.svg",
          "eye.svg",
          "file-earmark-medical.svg",
          "file-earmark-play.svg",
          "file-earmark-richtext.svg",
          "file-earmark-text.svg",
          "file-earmark-zip.svg",
          "filter.svg",
          "folder2-open.svg",
          "info-circle.svg",
          "info-circle-fill.svg",
          "journal-text.svg",
          "justify.svg",
          "lightning-charge.svg",
          "linkedin.svg",
          "newspaper.svg",
          "person-circle.svg",
          "question-lg.svg",
          "search.svg",
          "star.svg",
          "twitter.svg",
          "x.svg",
          "x-lg.svg",
          "x-square-fill.svg",
        ],
      },
    },
  ],
};
