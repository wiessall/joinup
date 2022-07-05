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
      entry: path.resolve(outputFolder, "src/js/shared-entity.js"),
      dest: path.resolve(outputFolder, "assets/js/shared-entity.js"),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/show-accordion-invalid-fields.js"),
      dest: path.resolve(outputFolder, "assets/js/show-accordion-invalid-fields.js"),
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
    // Main
    {
      entry: path.resolve(outputFolder, "src/scss/main.scss"),
      dest: path.resolve(outputFolder, "assets/css/main.min.css"),
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
      entry: path.resolve(outputFolder, "src/scss/add-content.scss"),
      dest: path.resolve(outputFolder, "assets/css/add-content.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/dropdown.scss"),
      dest: path.resolve(outputFolder, "assets/css/dropdown.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
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
      entry: path.resolve(outputFolder, "src/scss/members-overview-exposed-form.scss"),
      dest: path.resolve(outputFolder, "assets/css/members-overview-exposed-form.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/menu--og--navigation.scss"),
      dest: path.resolve(outputFolder, "assets/css/menu--og--navigation.min.css"),
      options: {
        includePaths,
        minify: true,
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
      entry: path.resolve(outputFolder, "src/scss/share-content-form.scss"),
      dest: path.resolve(outputFolder, "assets/css/share-content-form.min.css"),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/site-alerts-messages.scss"),
      dest: path.resolve(outputFolder, "assets/css/site-alerts-messages.min.css"),
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
      entry: path.resolve(outputFolder, "src/scss/tabs.scss"),
      dest: path.resolve(outputFolder, "assets/css/tabs.min.css"),
      options: {
        includePaths,
        minify: true,
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
      entry: path.resolve(outputFolder, "src/scss/three-dots-menu.scss"),
      dest: path.resolve(outputFolder, "assets/css/three-dots-menu.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // @todo ISAICP-7138 remove when switch to Ventuno theme is complete
    {
      entry: path.resolve(outputFolder, "src/scss/core/base/bootstrap-base.scss"),
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
        path.resolve(
          nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/*.css"
        ),
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
      from: [
        path.resolve(
          __dirname,
          "src/images/favicon/*"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images/favicon"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/homepage-banner/background.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/homepage-banner/banner-md.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/homepage-banner/wave.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/highlighted-event.jpg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/iop-eu-logo.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/iop-eu-header.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/logo.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          __dirname,
          "src/images/logo-ec--en.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    // Fontello icons
    {
      from: [
        path.resolve(
          __dirname,
          "src/fonts/joinup-icons/*.woff*"
        ),
      ],
      to: path.resolve(outputFolder, "assets/fonts/joinup-icons"),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(
          nodeModules,
          "@glidejs/glide/dist/css/glide.core.min.css"
        ),
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
        file: 'icons.svg',
        list: [
            'arrow-left.svg',
            'arrow-repeat.svg',
            'arrow-right.svg',
            'award.svg',
            'calendar.svg',
            'calendar3.svg',
            'card-list.svg',
            'chat.svg',
            'chat-left-text.svg',
            'check-circle-fill.svg',
            'chevron-bar-left.svg',
            'chevron-bar-right.svg',
            'chevron-down.svg',
            'chevron-left.svg',
            'chevron-right.svg',
            'clipboard-check.svg',
            'exclamation-circle-fill.svg',
            'exclamation-lg.svg',
            'eye.svg',
            'facebook.svg',
            'file-earmark-medical.svg',
            'file-earmark-post.svg',
            'file-earmark-play.svg',
            'file-earmark-richtext.svg',
            'file-earmark-text.svg',
            'file-earmark-zip.svg',
            'filter.svg',
            'folder2-open.svg',
            'info-circle.svg',
            'info-circle-fill.svg',
            'justify.svg',
            'lightning-charge.svg',
            'link-45deg.svg',
            'linkedin.svg',
            'newspaper.svg',
            'person-circle.svg',
            'pin-angle-fill.svg',
            'plus.svg',
            'search.svg',
            'star.svg',
            'three-dots-vertical.svg',
            'twitter.svg',
            'x.svg',
            'x-lg.svg',
            'x-square-fill.svg',
          ]
      },
    }
  ]
};
