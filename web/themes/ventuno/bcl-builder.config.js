const path = require("path");
const replace = require("@rollup/plugin-replace");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [
    {
      entry: path.resolve(outputFolder, "src/js/popovers.js"),
      dest: path.resolve(outputFolder, "assets/js/popovers.js"),
      options: {
        minify: false,
        sourceMap: true,
      },
    },
    {
      entry: path.resolve(outputFolder, "src/js/glide.js"),
      dest: path.resolve(outputFolder, "assets/js/glide.js"),
      options: {
        name: "glide",
        minify: false,
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
  ],
  styles: [
    {
      entry: path.resolve(outputFolder, "src/scss/main.scss"),
      dest: path.resolve(outputFolder, "assets/css/main.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/homepage.scss"),
      dest: path.resolve(outputFolder, "assets/css/homepage.css"),
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
    {
      entry: path.resolve(outputFolder, "src/scss/navbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar.css"),
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
      entry: path.resolve(outputFolder, "src/scss/search-bar.scss"),
      dest: path.resolve(outputFolder, "assets/css/search-bar.css"),
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
      entry: path.resolve(outputFolder, "src/scss/pager.scss"),
      dest: path.resolve(outputFolder, "assets/css/pager.css"),
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
    {
      entry: path.resolve(outputFolder, "src/scss/licence-comparer.scss"),
      dest: path.resolve(outputFolder, "assets/css/licence-comparer.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/footer.scss"),
      dest: path.resolve(outputFolder, "assets/css/footer.css"),
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
      entry: path.resolve(outputFolder, "src/scss/overrides/bootstrap-base.scss"),
      dest: path.resolve(outputFolder, "assets/css/bootstrap-base.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
  ],
  copy: [
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
    {
      from: [
        path.resolve(
          __dirname,
          "src/icons/bootstrap-icons.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/icons"),
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
          "src/images/homepage-banner.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
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
          "src/images/homepage-banner/banner-md.svg"
        ),
      ],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
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
};
