/**
 * @file
 * Configures BCL builder to generate assets.
 *
 * @see https://github.com/openeuropa/bootstrap-component-library/tree/development/builder
 */
const path = require("path");

const isProd = process.env.NODE_ENV === "production";

// Project paths configuration.
const paths = {
  baseDir: path.resolve(__dirname),
  nodeModules: path.resolve(__dirname, "node_modules"),
  themes: {
    ventuno: path.resolve(__dirname, "web/themes/ventuno/"),
    iop: path.resolve(__dirname, "web/themes/iop/"),
  },
};

/**
 * Helper to build BCL builder objects (scripts / styles).
 *
 * @param {Object} params
 *   Object containing:
 *   - {string} sourceFolder: Base folder for entry paths.
 *   - {string} destFolder: Base folder for dest paths.
 *   - {Object} defaultOptions: Default options applied to all objects.
 *   - {Array<Object>} objects: Array of config objects, each with:
 *     - {string} entry: Path to the source file, from sourceFolder.
 *     - {string} dest: Path to the destination file, from destFolder.
 *     - {Object} options: Optional object-specific options merged with defaults.
 *
 * @return {Array<Object>}
 *   Array of resolved objects.
 */
const buildAssetEntries = ({
  sourceFolder,
  destFolder,
  defaultOptions,
  objects,
}) =>
  objects.map(({ entry, dest, options = {} }) => ({
    entry: path.resolve(sourceFolder, entry),
    dest: path.resolve(destFolder, dest),
    options: {
      ...defaultOptions,
      ...options,
    },
  }));

module.exports = {
  scripts: [
    // Ventuno.
    ...buildAssetEntries({
      sourceFolder: path.resolve(paths.themes.ventuno, "src/js/"),
      destFolder: path.resolve(paths.themes.ventuno, "assets/js/"),
      defaultOptions: { minify: true, sourceMap: !isProd },
      objects: [
        "ajax-throbber.js",
        "alert.js",
        "checkbox.js",
        "duplicate-id-fixer.js",
        "navbar-hover-dropdown.js",
        "mmenu-nav.js",
        "explore-block.js",
        "fieldset-show-more.js",
        "highlight-invalid-fields.js",
        "carousel.js",
        "paragraph-inline-img.js",
        "private-message.js",
        "responsive-tables.js",
        "searchbox-focus.js",
        "shared-entity.js",
        "show-accordion-invalid-fields.js",
        "toggle-text-seemore-btn.js",
        "tour-first-balloon.js",
        "og-navigation.js",
        "sticky-groupheader.js",
        "actions-menu.js",
      ].map((file) => ({
        entry: file,
        dest: file,
      })),
    }),
    // IOP.
    ...buildAssetEntries({
      sourceFolder: path.resolve(paths.themes.iop, "src/js/"),
      destFolder: path.resolve(paths.themes.iop, "assets/js/"),
      defaultOptions: { minify: true, sourceMap: !isProd },
      objects: ["glide.js", "sticky-nav.js"].map((file) => ({
        entry: file,
        dest: file,
      })),
    }),
  ],
  styles: [
    // Ventuno.
    ...buildAssetEntries({
      sourceFolder: path.resolve(paths.themes.ventuno, "src/scss/"),
      destFolder: path.resolve(paths.themes.ventuno, "assets/css/"),
      defaultOptions: {
        includePaths: [paths.nodeModules],
        minify: true,
        sourceMap: isProd ? false : "file",
      },
      objects: [
        // Base (SMACSS)
        {
          entry: "01-base.scss",
          dest: "base.min.css",
        },
        // Layout (SMACSS)
        {
          entry: "02-layout.scss",
          dest: "layout.min.css",
        },
        // Components (SMACSS)
        {
          entry: "components/full-width-bg.scss",
          dest: "full-width-bg.min.css",
        },
        {
          entry: "03-components.scss",
          dest: "components.min.css",
        },
        // Emails
        {
          entry: "emails/mail.scss",
          dest: "emails/mail.min.css",
        },
        // Theme (SMACSS)
        {
          entry: "04-theme.scss",
          dest: "theme.min.css",
        },
        // Pages
        {
          entry: "pages/homepage.scss",
          dest: "homepage.min.css",
        },
        {
          entry: "pages/oss-landing.scss",
          dest: "oss-landing.min.css",
        },
        {
          entry: "pages/oss-solution-full.scss",
          dest: "oss-solution-full.min.css",
        },
        {
          entry: "pages/searchpage.scss",
          dest: "searchpage.min.css",
        },
        {
          entry: "pages/licence-comparer.scss",
          dest: "licence-comparer.min.css",
        },
        {
          entry: "pages/glossary.scss",
          dest: "glossary.min.css",
        },
        {
          entry: "pages/group.scss",
          dest: "group.min.css",
        },
        // Components
        {
          entry: "components/abstract.scss",
          dest: "abstract.min.css",
        },
        {
          entry: "components/accordion.scss",
          dest: "accordion.min.css",
        },
        {
          entry: "components/actions-menu.scss",
          dest: "actions-menu.min.css",
        },
        {
          entry: "components/alert.scss",
          dest: "alert.min.css",
        },
        {
          entry: "modules/align-module.scss",
          dest: "align-module.min.css",
        },
        {
          entry: "components/badge.scss",
          dest: "badge.min.css",
        },
        {
          entry: "components/button-group.scss",
          dest: "button-group.min.css",
        },
        {
          entry: "components/close.scss",
          dest: "close.min.css",
        },
        {
          entry: "components/comments.scss",
          dest: "comments.min.css",
        },
        {
          entry: "components/eif-filter.scss",
          dest: "eif-filter.min.css",
        },
        {
          entry: "components/etrans.scss",
          dest: "etrans.min.css",
        },
        {
          entry: "components/ajax-throbber.scss",
          dest: "ajax-throbber.min.css",
        },
        {
          entry: "components/fieldset-show-more.scss",
          dest: "fieldset-show-more.min.css",
        },
        {
          entry: "components/footer.scss",
          dest: "footer.min.css",
        },
        {
          entry: "components/form-simple-checkboxes.scss",
          dest: "form-simple-checkboxes.min.css",
        },
        {
          entry: "components/forms.scss",
          dest: "forms.min.css",
        },
        {
          entry: "components/webform.scss",
          dest: "webform.min.css",
        },
        {
          entry: "components/group-banner.scss",
          dest: "group-banner.min.css",
        },
        {
          entry: "components/header-topic.scss",
          dest: "header-topic.min.css",
        },
        {
          entry: "components/ief-entity-table.scss",
          dest: "ief-entity-table.min.css",
        },
        {
          entry: "components/language-picker.scss",
          dest: "language-picker.min.css",
        },
        {
          entry: "components/layout-block.scss",
          dest: "layout-block.min.css",
        },
        {
          entry: "components/licence-filter.scss",
          dest: "licence-filter.min.css",
        },
        {
          entry: "components/listing-grid.scss",
          dest: "listing-grid.min.css",
        },
        {
          entry: "components/menu--og--navigation.scss",
          dest: "menu--og--navigation.min.css",
        },
        {
          entry: "components/modals.scss",
          dest: "modals.min.css",
        },
        {
          entry: "components/nav.scss",
          dest: "nav.min.css",
        },
        {
          entry: "components/mmenu.scss",
          dest: "mmenu.min.css",
        },
        {
          entry: "components/navbar-search.scss",
          dest: "navbar-search.min.css",
        },
        {
          entry: "components/navbar.scss",
          dest: "navbar.min.css",
        },
        {
          entry: "components/navigation-bottom.scss",
          dest: "navigation-bottom.min.css",
        },
        {
          entry: "components/page-title.scss",
          dest: "page-title.min.css",
        },
        {
          entry: "components/pagination.scss",
          dest: "pagination.min.css",
        },
        {
          entry: "components/paragraphs/paragraph.scss",
          dest: "paragraph.min.css",
        },
        {
          entry: "components/private-message.scss",
          dest: "private-message.min.css",
        },
        {
          entry: "components/carousel.scss",
          dest: "carousel.min.css",
        },
        {
          entry: "components/image-gallery.scss",
          dest: "image-gallery.min.css",
        },
        {
          entry: "components/inline-items.scss",
          dest: "inline-items.min.css",
        },
        {
          entry: "components/swagger-ui-integration.scss",
          dest: "swagger-ui-integration.min.css",
        },
        {
          entry: "components/oss-solution-indicator.scss",
          dest: "oss-solution-indicator.min.css",
        },
        {
          entry: "components/oss-solution-screenshots-carousel.scss",
          dest: "oss-solution-screenshots-carousel.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--heading.scss",
          dest: "paragraph--heading.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--layout.scss",
          dest: "paragraph--layout.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--poll.scss",
          dest: "paragraph--poll.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--social-media.scss",
          dest: "paragraph--social-media.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--table-of-contents.scss",
          dest: "paragraph--table-of-contents.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--testimony.scss",
          dest: "paragraph--testimony.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--tiles.scss",
          dest: "paragraph--tiles.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--video.scss",
          dest: "paragraph--video.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--events-calendar.scss",
          dest: "paragraph--events-calendar.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--iframe.scss",
          dest: "paragraph--iframe.min.css",
        },
        {
          entry: "components/releases.scss",
          dest: "releases.min.css",
        },
        {
          entry: "components/share-content-form.scss",
          dest: "share-content-form.min.css",
        },
        {
          entry: "components/see-more.scss",
          dest: "see-more.min.css",
        },
        {
          entry: "components/spinners.scss",
          dest: "spinners.min.css",
        },
        {
          entry: "components/table-files.scss",
          dest: "table-files.min.css",
        },
        {
          entry: "components/tabledrag.scss",
          dest: "tabledrag.min.css",
        },
        {
          entry: "components/tables.scss",
          dest: "tables.min.css",
        },
        {
          entry: "components/tabs.scss",
          dest: "tabs.min.css",
        },
        {
          entry: "components/tile.scss",
          dest: "tile.min.css",
        },
        {
          entry: "components/timeline.scss",
          dest: "timeline.min.css",
        },
        {
          entry: "components/toolbar.scss",
          dest: "toolbar.min.css",
        },
        {
          entry: "components/tour.scss",
          dest: "tour.min.css",
        },
        {
          entry: "components/user--group-header.scss",
          dest: "user--group-header.min.css",
        },
        // Layout Paragraphs - buidler UI.
        {
          entry: "components/layout-paragraphs--builder.scss",
          dest: "layout-paragraphs--builder.min.css",
        },
        // Editor.
        {
          entry: "editor.layout-paragraphs.scss",
          dest: "editor.layout-paragraphs.min.css",
        },
      ],
    }),
    // IOP.
    ...buildAssetEntries({
      sourceFolder: path.resolve(paths.themes.iop, "src/scss/"),
      destFolder: path.resolve(paths.themes.iop, "assets/css/"),
      defaultOptions: {
        includePaths: [paths.nodeModules],
        minify: true,
        sourceMap: isProd ? false : "file",
      },
      objects: [
        // Components (SMACSS)
        {
          entry: "03-components.scss",
          dest: "components.min.css",
        },
        // Theme (SMACSS)
        {
          entry: "04-theme.scss",
          dest: "theme.min.css",
        },
        // Pages
        {
          entry: "pages/landing.scss",
          dest: "landing.min.css",
        },
        {
          entry: "pages/listing.scss",
          dest: "listing.min.css",
        },
        // Components
        {
          entry: "components/accordion-faq.scss",
          dest: "accordion-faq.min.css",
        },
        {
          entry: "components/call-to-action.scss",
          dest: "call-to-action.min.css",
        },
        {
          entry: "components/join.scss",
          dest: "join.min.css",
        },
        {
          entry: "components/navbar.scss",
          dest: "navbar.min.css",
        },
        {
          entry: "components/page-header.scss",
          dest: "page-header.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--call-to-action.scss",
          dest: "paragraph--call-to-action.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--carousel.scss",
          dest: "paragraph--carousel.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--image.scss",
          dest: "paragraph--image.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--layout.scss",
          dest: "paragraph--layout.min.css",
        },
        {
          entry: "components/paragraphs/paragraph--testimony.scss",
          dest: "paragraph--testimony.min.css",
        },
        {
          entry: "components/tile.scss",
          dest: "tile.min.css",
        },
        {
          entry: "components/pagination.scss",
          dest: "pagination.min.css",
        },
      ],
    }),
  ],
  copy: [
    // Bootstrap: fonts
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/*.css",
        ),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/css/fonts"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/files/*.woff*",
        ),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/css/fonts/files"),
      options: { up: true },
    },
    // Bootstrap: scripts
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/js/oe-bcl-joinup.bundle.min.js",
        ),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/js"),
      options: { up: true },
    },
    // Bootstrap: icons
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-bootstrap/icons/*.svg",
        ),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/icons"),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@glidejs/glide/dist/css/glide.core.min.css",
        ),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/css"),
      options: { up: true },
    },
    // IOP
    // Bootstrap: fonts
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/*.css",
        ),
      ],
      to: path.resolve(paths.themes.iop, "assets/css/fonts"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/files/*.woff*",
        ),
      ],
      to: path.resolve(paths.themes.iop, "assets/css/fonts/files"),
      options: { up: true },
    },
    // Bootstrap: scripts
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@openeuropa/bcl-theme-joinup/js/oe-bcl-joinup.bundle.min.js",
        ),
      ],
      to: path.resolve(paths.themes.iop, "assets/js"),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(
          paths.nodeModules,
          "@glidejs/glide/dist/css/glide.core.min.css",
        ),
      ],
      to: path.resolve(paths.themes.iop, "assets/css"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(paths.nodeModules, "priority-plus/dist/priority-plus.js"),
        path.resolve(paths.nodeModules, "priority-plus/dist/priority-plus.css"),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/lib/priority-plus"),
      options: { up: true },
    },
    {
      from: [
        path.resolve(paths.nodeModules, "mmenu-light/dist/mmenu-light.js"),
        path.resolve(paths.nodeModules, "mmenu-light/dist/mmenu-light.css"),
      ],
      to: path.resolve(paths.themes.ventuno, "assets/lib/mmenu-light"),
      options: { up: true },
    },
    // Third party libraries deployed under web/libraries to satisfy contrib
    // module library definitions (e.g. /libraries/<name>/...).
    {
      from: [path.resolve(paths.nodeModules, "chart.js/dist/**/*")],
      to: path.resolve(__dirname, "web/libraries/chart.js/dist"),
      options: { up: 3 },
    },
    {
      from: [path.resolve(paths.nodeModules, "dompurify/dist/**/*")],
      to: path.resolve(__dirname, "web/libraries/dompurify/dist"),
      options: { up: 3 },
    },
    {
      from: [path.resolve(paths.nodeModules, "jquery-jsonview/dist/**/*")],
      to: path.resolve(__dirname, "web/libraries/jquery-jsonview/dist"),
      options: { up: 3 },
    },
    {
      from: [path.resolve(paths.nodeModules, "js-cookie/dist/**/*")],
      to: path.resolve(__dirname, "web/libraries/js-cookie/dist"),
      options: { up: 3 },
    },
    {
      from: [path.resolve(paths.nodeModules, "slick-carousel/slick/**/*")],
      to: path.resolve(__dirname, "web/libraries/slick/slick"),
      options: { up: 3 },
    },
    {
      from: [path.resolve(paths.nodeModules, "@yaireo/tagify/dist/**/*")],
      to: path.resolve(__dirname, "web/libraries/tagify/dist"),
      options: { up: 4 },
    },
  ],
  sprite: [
    {
      entry: path.resolve(
        paths.nodeModules,
        "@openeuropa/bcl-bootstrap/icons/",
      ),
      dest: path.resolve(paths.themes.ventuno, "assets/icons"),
      options: {
        file: "icons.svg",
        list: [
          "archive.svg",
          "archive-fill.svg",
          "arrow-left.svg",
          "arrow-repeat.svg",
          "arrow-right.svg",
          "award.svg",
          "bookmark.svg",
          "bookmark-check-fill.svg",
          "box-arrow-up-right.svg",
          "calendar.svg",
          "calendar-event.svg",
          "card-list.svg",
          "caret-down-fill.svg",
          "chat.svg",
          "chat-dots.svg",
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
          "code-slash.svg",
          "download.svg",
          "envelope-fill.svg",
          "exclamation-circle-fill.svg",
          "exclamation-triangle-fill.svg",
          "exclamation-lg.svg",
          "exclamation-triangle-fill.svg",
          "eye.svg",
          "facebook.svg",
          "file-earmark.svg",
          "file-earmark-check.svg",
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
          "hand-thumbs-up.svg",
          "hand-thumbs-up-fill.svg",
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
          "patch-check.svg",
          "pencil-square.svg",
          "people.svg",
          "person-circle.svg",
          "person-workspace.svg",
          "pin-angle-fill.svg",
          "play-btn.svg",
          "plus.svg",
          "question-lg.svg",
          "reply.svg",
          "rss.svg",
          "search.svg",
          "star.svg",
          "three-dots.svg",
          "trash.svg",
          "type.svg",
          "twitter.svg",
          "vimeo.svg",
          "x-circle-fill.svg",
          "x-lg.svg",
          "x-square-fill.svg",
          "x.svg",
          "youtube.svg",
        ],
      },
    },
  ],
};
