# Ventuno for Interoperable Europe Portal theme

Custom theme for Interoperable Europe Portal. Built on the [OpenEuropa Bootstrap
theme](https://github.com/openeuropa/oe_bootstrap_theme).

The recommended development workflow is to use Docker. See the main README file
in the project root for instructions.

## Table of contents

* [Requirements](#requirements)
* [Development workflow](#development-workflow)
* [Structure](#structure)
* [Compilation](#compilation)
  * [Icons](#icons)
* [Patterns](#patterns)
* [Notes](#notes)

## Requirements

All requirements are included in the Docker image. If you are not using Docker
you will need to install the following dependencies:

* [Node.js](https://nodejs.org/en/)
* [npm](https://www.npmjs.com/)

## Development workflow

The code is publicly available at
[https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference](https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference)
as part of the Interoperable Europe Portal project. The development is coordinated in a private Jira
instance: [https://citnet.tech.ec.europa.eu/CITnet/jira/projects/ISAICP](https://citnet.tech.ec.europa.eu/CITnet/jira/projects/ISAICP).

When frontend development is requested, in most cases this will be preceded by a
backend ticket that will be developed first. After the backend work is reviewed
and approved in user acceptance testing a separate frontend sister ticket is
created. Since the backend and frontend will need to be delivered to production
simultaneously, the frontend ticket will be forked off the branch that contains
the backend functionality. In Interoperable Europe Portal parlance the combination of both tickets is
called a "mini-epic". Once the FE ticket is reviewed and accepted, both will be
merged into the `develop` branch to be included in the next release.

## Structure

The theme is built using the OpenEuropa Bootstrap theme. The theme is built
using the [Bootstrap 4](https://getbootstrap.com/) framework. The theme is
built using the [Sass](https://sass-lang.com/) preprocessor.

The key structure of the theme is as follows:

* `assets` directory holds compiled CSS and JS files as well as images and
fonts. **Important**: do not edit files in this directory directly. They are
compiled from the `src` directory. However, **keep in mind** that the `assets`
directory is committed to the repository.
* `includes` directory holds fractions of the main `.theme` file. Since the
theming functions are numerous, they are split into separate files for easier
maintenance. File naming and contents are self-explanatory.
* `src` directory holds the source files for the theme. This is where you should
make your changes. The `src` directory is not committed to the repository. 2
subdirectories are available, the `js` and the `scss` directories which hold the
corresponding files. Like `includes`, files are split grouped according to their
contents. File naming and contents are self-explanatory.
* `templates` directory holds the Twig templates for the theme.

Further information can be found at the `ventuno.info.yml` and
`ventuno.libraries.yml` files.

## Compilation

To compile the assets, simply run `npm run build` **from the root of the
project**. If you are working on the assets in a frontend ticket, you can either
run `npm run build` everytime you are done with your changes, or you can run
`npm run watch` to compile the assets on the fly. In project root, there is the
`bcl-builder.config.js` which holds the configuration for the compilation of the
assets as well as the icons.

### Icons

The theme uses a list of icons provided by the
[https://github.com/openeuropa/bootstrap-component-library](https://github.com/openeuropa/bootstrap-component-library).
The icons that are compiled are defined in the `bcl-builder.config.js` file. The
icons are compiled in the `assets/icons/icons.svg` file. BCL also provides a way
to use the icons in the theme. In order to use them in the templates, you can
use the `pattern` function. For example, if you want to use the `arrow-right`
icon, you can use the following code:

```twig
{{ pattern('icon', {
  name: 'arrow-right',
}) }}
```
See `bcl-icon.html.twig` for variables and references.

## Patterns

The
[Bootstrap Component Library](https://github.com/openeuropa/bootstrap-component-library)
that the theme uses, provides a list of patterns that can be used in the theme.
Some patterns are already used, like the `bcl-badge` and `bcl-icon`. For the
full list of patterns, you can check the `oe_bootstrap_theme/assets/bcl`
directory.

## Notes

This guide should be expanded with more practices used by the Interoperable Europe Portal project.
Things possibly missing:
* Better way to be able to view BCL components and understand how to interact
with them.
* Cases where we are not using the BCL components (like images) and how to
differentiate them when choosing the most suitable way.
* Restrictions about practices we are not using in our twig files, or some
specific development practices (like, what we put in the `includes` directory
and what goes into the twig files, etc.).
