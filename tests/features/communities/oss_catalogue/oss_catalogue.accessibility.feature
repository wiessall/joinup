@api @group-clone @javascript @eu-oss-catalogue
Feature: Accessibility

  Scenario Outline: Accessibility compliance checks.
    And the following "oss_solution" content:
      | title       | oss_categories      | oss_default_branch | oss_development_status | oss_git_description | oss_git_forks | oss_git_open_issues | oss_git_open_pull_requests | oss_git_stars | oss_git_url                       | oss_hosting_platform | oss_landing_url   | oss_license | oss_maintenance_type | oss_publiccode_yml_version | oss_release_date | oss_scope  | oss_software_type | oss_software_version | oss_source        | oss_vitality_index |
      | IO solution | digital-citizenship | master             | beta                   | GIT description     | 105           | 16                  | 19                         | 645           | https://github.com/pagopa/io-test | github               | https://ioapp.it/ | EUPL-1.2    | internal             | 0.2                        | 2025-03-06       | government | standalone/mobile | 3.0.0-rc.8           | developers_italia | 0.56               |

    When I visit "<page>"
    And the page meets accessibility standards

    Examples:
      | page                                    |
      | /eu-oss-catalogue                       |
      | /eu-oss-catalogue/solutions             |
      | /eu-oss-catalogue/solutions/io-solution |
      | /eu-oss-catalogue/about                 |
