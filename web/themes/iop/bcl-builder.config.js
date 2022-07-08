const path = require("path");
const replace = require("@rollup/plugin-replace");

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
    // Theme images
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
  ],
  sprite: []
};
