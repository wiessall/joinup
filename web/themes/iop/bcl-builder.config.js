/**
 * @file
 * BCL builder configuration.
 */
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
    // Components (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/03-components.scss"),
      dest: path.resolve(outputFolder, "assets/css/components.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // Theme (SMACSS)
    {
      entry: path.resolve(outputFolder, "src/scss/04-theme.scss"),
      dest: path.resolve(outputFolder, "assets/css/theme.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    // Pages
    {
      entry: path.resolve(outputFolder, "src/scss/pages/listing.scss"),
      dest: path.resolve(outputFolder, "assets/css/listing.min.css"),
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
        "src/scss/components/accordion-faq.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/accordion-faq.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/join.scss"),
      dest: path.resolve(outputFolder, "assets/css/join.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/navbar.scss"),
      dest: path.resolve(outputFolder, "assets/css/navbar.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/page-header.scss"),
      dest: path.resolve(outputFolder, "assets/css/page-header.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/paragraph.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph--2-column-featured-left.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--2-column-featured-left.min.css"
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
        "src/scss/components/paragraphs/paragraph--2-column-featured-right.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--2-column-featured-right.min.css"
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
        "src/scss/components/paragraphs/paragraph--call-to-action.scss"
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
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph--carousel.scss"
      ),
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
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph--image.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/paragraph--image.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph--row.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/paragraph--row.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(
        outputFolder,
        "src/scss/components/paragraphs/paragraph--testimony.scss"
      ),
      dest: path.resolve(
        outputFolder,
        "assets/css/paragraph--testimony.min.css"
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
        "src/scss/components/paragraphs/paragraph--video.scss"
      ),
      dest: path.resolve(outputFolder, "assets/css/paragraph--video.min.css"),
      options: {
        minify: true,
        includePaths,
        sourceMap: "file",
      },
    },
    {
      entry: path.resolve(outputFolder, "src/scss/components/pagination.scss"),
      dest: path.resolve(outputFolder, "assets/css/pagination.min.css"),
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
    // Third party files
    {
      from: [
        path.resolve(nodeModules, "@glidejs/glide/dist/css/glide.core.min.css"),
      ],
      to: path.resolve(outputFolder, "assets/css"),
      options: { up: true },
    },
  ],
};
