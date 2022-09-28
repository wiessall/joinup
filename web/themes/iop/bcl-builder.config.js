const path = require("path");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [
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
    // Components
    {
      entry: path.resolve(outputFolder, "src/scss/accordion-faq.scss"),
      dest: path.resolve(outputFolder, "assets/css/accordion-faq.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components.scss"),
      dest: path.resolve(outputFolder, "assets/css/components.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/join.scss"),
      dest: path.resolve(outputFolder, "assets/css/join.min.css"),
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
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/page-header.scss"),
      dest: path.resolve(outputFolder, "assets/css/page-header.min.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph.scss"),
      dest: path.resolve(outputFolder, "assets/css/paragraph.min.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/paragraph--2-column-featured-left.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--2-column-featured-left.min.css"
      ),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/paragraph--2-column-featured-right.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--2-column-featured-right.min.css"
      ),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/paragraph--call-to-action.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--call-to-action.min.css"
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph--carousel.scss"),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--carousel.min.css"
      ),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph--image.scss"),
      dest: path.resolve(outputFolder, "assets/css/paragraph--image.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph--row.scss"),
      dest: path.resolve(outputFolder, "assets/css/paragraph--row.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph--testimony.scss"),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--testimony.min.css"
      ),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/paragraph--video.scss"),
      dest: path.resolve(outputFolder, "assets/css/paragraph--video.min.css"),
      options: {
        includePaths,
        sourceMap: "file",
      },
    },
    // Theme
    {
      entry: path.resolve(outputFolder, "src/scss/theme.scss"),
      dest: path.resolve(outputFolder, "assets/css/theme.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/listing.scss"),
      dest: path.resolve(outputFolder, "assets/css/listing.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/pager.scss"),
      dest: path.resolve(outputFolder, "assets/css/pager.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // @todo ISAICP-7138 delete this library when switch to Ventuno theme is complete
    {
      entry: path.resolve(outputFolder, "src/scss/add-content.scss"),
      dest: path.resolve(outputFolder, "assets/css/add-content.min.css"),
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
    // Theme images
    {
      from: [path.resolve(__dirname, "src/images/hamburger-menu-icon.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/iop-eu-logo.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/banners/ACT.webp")],
      to: path.resolve(outputFolder, "assets/images/banners/"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/banners/events.webp")],
      to: path.resolve(outputFolder, "assets/images/banners/"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/banners/FAQ.webp")],
      to: path.resolve(outputFolder, "assets/images/banners/"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/banners/news.webp")],
      to: path.resolve(outputFolder, "assets/images/banners/"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/banners/policy.webp")],
      to: path.resolve(outputFolder, "assets/images/banners/"),
      options: { up: true },
    },
    {
      from: [path.resolve(__dirname, "src/images/join-bg.png")],
      to: path.resolve(outputFolder, "assets/images"),
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
  sprite: [],
};
