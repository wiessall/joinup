/**
 * @file
 * BCL builder config for copying third party libraries from node_modules into
 * web/libraries/, so contrib modules can resolve them at their expected paths
 * (e.g. /libraries/<name>/...). Run via `npm run build:third-party-libraries`
 * (also chained from `postinstall`).
 *
 * Kept separate from bcl-builder.config.js so the regular theme build
 * (build:copy / build:scripts / build:styles) does not redo library copies on
 * every run — node_modules only changes on install.
 */
const path = require("path");

// Paths are relative to the project root so bcl-builder's `up` option (which
// strips path segments from the start of each globbed file) can be derived
// from the source depth. Add a new entry here to wire it into both the copy
// task and (transitively) `postinstall`.
const thirdPartyLibraries = [
  {
    from: "node_modules/chart.js/dist",
    to: "web/libraries/chart.js/dist",
    up: 3,
  },
  {
    from: "node_modules/dompurify/dist",
    to: "web/libraries/dompurify/dist",
    up: 3,
  },
  {
    from: "node_modules/jquery-colorbox",
    to: "web/libraries/colorbox",
    up: 2,
  },
  {
    from: "node_modules/jquery-jsonview/dist",
    to: "web/libraries/jquery-jsonview/dist",
    up: 3,
  },
  {
    from: "node_modules/js-cookie/dist",
    to: "web/libraries/js-cookie/dist",
    up: 3,
  },
  {
    from: "node_modules/slick-carousel/slick",
    to: "web/libraries/slick/slick",
    up: 3,
  },
  {
    from: "node_modules/@yaireo/tagify/dist",
    to: "web/libraries/tagify/dist",
    up: 4,
  },
];

module.exports = {
  copy: thirdPartyLibraries.map(({ from, to, up }) => ({
    from: [`${from}/**/*`],
    to: path.resolve(__dirname, to),
    options: { up },
  })),
};
