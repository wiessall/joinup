GitHub Feed module
==================

This module provides a field type that allows you to display a feed of a GitHub
repository on your site.

## Setup

To set up the module, you will need the `JOINUP_GITHUB_TOKEN` environment
variable set to a
[GitHub personal access token](https://github.com/settings/tokens). This token
should have the `public_repo` scope. The token will be referenced in the
`settings.php` file automatically. For testing purposes, you can skip the token
but you will be limited to 60 requests per hour.

## Usage

To use the module, add a field of type `GitHub Feed` to a content type. The
field will have a `Repository` setting, which should be set to the GitHub
repository you want to display. The module supports issues, pull requests and
activity feeds.

The repository should be public as the token used to access it will not have
access to private repositories.
