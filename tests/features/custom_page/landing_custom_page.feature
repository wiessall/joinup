@api @group-d
Feature: Landing custom page.
  In order to create landing custom pages
  As a moderator
  I need to be able to manage "Landing Custom Page" content through UI.

  Scenario: Manage custom landing pages
    Given the following "collection" content:
      | title     |
      | Roman law |
    And the following "custom_page" content:
      | title                       | collection |
      | Early law and jurisprudence | Roman law  |

    Given I am logged in as a "moderator"

    When I visit "/dashboard"
    And I click "Custom landing pages"
    And I should see the heading "Custom landing pages"
    And I should see the link "Add custom landing page"
    But I should not see "Early law and jurisprudence"

    When I click "Add custom landing page"
    And the following field should be present "URL alias"
    And I fill in the following:
      | Title    | Twelve Tables          |
      | Abstract | Twelve tables abstract |
    And I select "Data gathering and processing" options from "Topic"
    And I press "Publish"

    When I visit "/admin/custom-landing-pages"
    Then I should see the link "Twelve Tables"

    #  Page without group should not generate any errors.
    Given I am an anonymous user
    When I visit the "Twelve Tables" "custom page"
    And I see h1 span element with title "Twelve Tables"

  @javascript
  Scenario Outline: Limited aliases for landing page
    Given I am logged in as a "moderator"
    When I visit "/admin/custom-landing-pages/add"
    And I fill in "Alias test" for "Title"
    And I select "Data gathering and processing" options from "Topic"
    And I press "URL alias"
    And I uncheck the box "Generate automatic URL alias"
    And I fill in "<path>" for "URL alias"

    And I press "Publish"
    Then I should see the inline form error message "<path> can not be used for URL alias field"

    Examples:
      | path         |
      | /search      |
      | /search/test |
