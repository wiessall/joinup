const path = require("path");

const outputFolder = path.resolve(__dirname);
const nodeModules = "./node_modules";

// SCSS includePaths
const includePaths = [nodeModules];

module.exports = {
  scripts: [],
  styles: [
    // Components
    {
      entry: path.resolve(outputFolder, "src/scss/components.scss"),
      dest: path.resolve(outputFolder, "assets/css/components.min.css"),
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
    // Pages
    {
      entry: path.resolve(outputFolder, "src/scss/homepage.scss"),
      dest: path.resolve(outputFolder, "assets/css/homepage.min.css"),
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
    // Spare components
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
      from: [path.resolve(__dirname, "src/images/iop-eu-logo.svg")],
      to: path.resolve(outputFolder, "assets/images"),
      options: { up: true },
    },
  ],
  sprite: [],
};
