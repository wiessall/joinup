/**
 * @file
 * Configures BCL builder to generate assets.
 */
const path = require("path");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

const ventuno = "web/themes/ventuno/";
const iop = "web/themes/iop/";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [
    // Ventuno
    {
      entry: path.resolve(outputFolder, `${ventuno}src/js/ajax-throbber.js`),
      dest: path.resolve(outputFolder, `${ventuno}assets/js/ajax-throbber.js`),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, `${ventuno}src/js/checkbox.js`),
      dest: path.resolve(outputFolder, `${ventuno}assets/js/checkbox.js`),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/navbar-hover-dropdown.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/navbar-hover-dropdown.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, `${ventuno}src/js/explore-block.js`),
      dest: path.resolve(outputFolder, `${ventuno}assets/js/explore-block.js`),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/fieldset-show-more.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/fieldset-show-more.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/highlight-invalid-fields.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/highlight-invalid-fields.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/paragraph--carousel.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/paragraph--carousel.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/paragraph-inline-img.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/paragraph-inline-img.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/responsive-tables.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/responsive-tables.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, `${ventuno}src/js/searchbox-focus.js`),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/searchbox-focus.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, `${ventuno}src/js/shared-entity.js`),
      dest: path.resolve(outputFolder, `${ventuno}assets/js/shared-entity.js`),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/show-accordion-invalid-fields.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/show-accordion-invalid-fields.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/sticky-menu-logged-in.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/sticky-menu-logged-in.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/toggle-text-seemore-btn.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/toggle-text-seemore-btn.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/js/tour-first-balloon.js`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/js/tour-first-balloon.js`
      ),
      options: {
        minify: true,
        sourceMap: true,
      },
    },
    // IOP
    {
      entry: path.resolve(outputFolder, `${iop}src/js/glide.js`),
      dest: path.resolve(outputFolder, `${iop}assets/js/glide.js`),
      options: {
        name: "glide",
        minify: true,
        sourceMap: true,
      },
    },
  ],
  styles: [
    // Ventuno
    // Base (SMACSS)
    {
      entry: path.resolve(outputFolder, `${ventuno}src/scss/01-base.scss`),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/base.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Layout (SMACSS)
    {
      entry: path.resolve(outputFolder, `${ventuno}src/scss/02-layout.scss`),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/layout.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Components (SMACSS)
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/03-components.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/components.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Theme (SMACSS)
    {
      entry: path.resolve(outputFolder, `${ventuno}src/scss/04-theme.scss`),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/theme.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/pages/homepage.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/homepage.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/pages/searchpage.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/searchpage.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/pages/licence-comparer.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/licence-comparer.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/pages/glossary.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/glossary.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, `${ventuno}src/scss/pages/group.scss`),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/group.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Components
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/abstract.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/abstract.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/accordion.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/accordion.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/actions-menu.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/actions-menu.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/alert.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/alert.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/badge.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/badge.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/button-group.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/button-group.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/close.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/close.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/comments.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/comments.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/eif-filter.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/eif-filter.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/etrans.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/etrans.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/fieldset-show-more.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/fieldset-show-more.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/footer.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/footer.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/form-checkboxes.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/form-checkboxes.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/forms.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/forms.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/webform.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/webform.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/group-banner.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/group-banner.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/header-topic.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/header-topic.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/ief-entity-table.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/ief-entity-table.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/layout-block.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/layout-block.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/licence-filter.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/licence-filter.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/listing-grid.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/listing-grid.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/menu--og--navigation.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/menu--og--navigation.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/modals.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/modals.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/nav.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/nav.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/navbar-search.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/navbar-search.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/navbar.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/navbar.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/navigation-bottom.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/navigation-bottom.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/page-title.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/page-title.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/pagination.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/pagination.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--carousel.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--carousel.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--heading.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--heading.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--layout.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--layout.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--poll.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--poll.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--social-media.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--social-media.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--testimony.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--testimony.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--tiles.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--tiles.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/paragraphs/paragraph--video.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/paragraph--video.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/releases.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/releases.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/share-content-form.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/share-content-form.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/see-more.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/see-more.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/spinners.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/spinners.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/table-files.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/table-files.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/tabledrag.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/tabledrag.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/tables.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/tables.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/tabs.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/tabs.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/tile.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/tile.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/timeline.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/timeline.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/toolbar.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/toolbar.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/tour.scss`
      ),
      dest: path.resolve(outputFolder, `${ventuno}assets/css/tour.min.css`),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/user--group-header.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/user--group-header.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // Layout Paragraphs - buidler UI.
    {
      entry: path.resolve(
        outputFolder,
        `${ventuno}src/scss/components/layout-paragraphs--builder.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${ventuno}assets/css/layout-paragraphs--builder.min.css`
      ),
      options: {
        includePaths,
        minify: true,
        sourceMap: "file",
      },
    },
    // IOP
    // Components (SMACSS)
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/03-components.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/components.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // Theme (SMACSS)
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/04-theme.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/theme.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/pages/landing.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/landing.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/pages/listing.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/listing.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // Components
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/accordion-faq.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/accordion-faq.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/call-to-action.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/call-to-action.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/components/join.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/join.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/navbar.scss`
      ),
      dest: path.resolve(outputFolder, `${iop}assets/css/navbar.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/page-header.scss`
      ),
      dest: path.resolve(outputFolder, `${iop}assets/css/page-header.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/paragraphs/paragraph--call-to-action.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/paragraph--call-to-action.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/paragraphs/paragraph--carousel.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/paragraph--carousel.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/paragraphs/paragraph--image.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/paragraph--image.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/paragraphs/paragraph--layout.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/paragraph--layout.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/paragraphs/paragraph--testimony.scss`
      ),
      dest: path.resolve(
        outputFolder,
        `${iop}assets/css/paragraph--testimony.min.css`
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, `${iop}src/scss/components/tile.scss`),
      dest: path.resolve(outputFolder, `${iop}assets/css/tile.min.css`),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        `${iop}src/scss/components/pagination.scss`
      ),
      dest: path.resolve(outputFolder, `${iop}assets/css/pagination.min.css`),
      options: {
        minify: true,
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
      to: path.resolve(outputFolder, `${ventuno}assets/css/fonts`),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/files/*.woff*"
        ),
      ],
      to: path.resolve(outputFolder, `${ventuno}assets/css/fonts/files`),
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
      to: path.resolve(outputFolder, `${ventuno}assets/js`),
      options: { up: true },
    },
    // Bootstrap: icons
    {
      from: [
        path.resolve(nodeModules, "@openeuropa/bcl-bootstrap/icons/*.svg"),
      ],
      to: path.resolve(outputFolder, `${ventuno}assets/icons`),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(nodeModules, "@glidejs/glide/dist/css/glide.core.min.css"),
      ],
      to: path.resolve(outputFolder, `${ventuno}assets/css`),
      options: { up: true },
    },
    // IOP
    // Bootstrap: fonts
    {
      from: [
        path.resolve(nodeModules, "@openeuropa/bcl-theme-joinup/fonts/*.css"),
      ],
      to: path.resolve(outputFolder, `${iop}assets/css/fonts`),
      options: { up: true },
    },
    {
      from: [
        path.resolve(
          nodeModules,
          "@openeuropa/bcl-theme-joinup/fonts/files/*.woff*"
        ),
      ],
      to: path.resolve(outputFolder, `${iop}assets/css/fonts/files`),
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
      to: path.resolve(outputFolder, `${iop}assets/js`),
      options: { up: true },
    },
    // Third party files
    {
      from: [
        path.resolve(nodeModules, "@glidejs/glide/dist/css/glide.core.min.css"),
      ],
      to: path.resolve(outputFolder, `${iop}assets/css`),
      options: { up: true },
    },
  ],
  sprite: [
    {
      entry: path.resolve(nodeModules, "@openeuropa/bcl-bootstrap/icons/"),
      dest: path.resolve(outputFolder, `${ventuno}assets/icons`),
      options: {
        file: "icons.svg",
        list: [
          "archive.svg",
          "archive-fill.svg",
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
