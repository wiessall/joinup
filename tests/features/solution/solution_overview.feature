@api @group-i
Feature: Solutions Overview
  As a new visitor of the Interoperable Europe Portal website
  To get an idea of the various solutions that are available
  I should see a list of all solutions

  Scenario: Solution overview paging
    Given the following "solution" content:
      | title      | creation date     |
      | Arctic fox | 2018-10-04 8:21am |
      | Alpaca     | 2018-10-04 8:31am |
      | Boomalope  | 2018-10-04 8:28am |
      | Boomrat    | 2018-10-04 8:35am |
      | Megasloth  | 2018-10-04 8:01am |
      | Thrumbo    | 2018-10-04 8:07am |
      | Spelopede  | 2018-10-04 8:18am |
      | Muffalo    | 2018-10-04 8:59am |
      | Husky      | 2018-10-04 8:00am |
      | Gazelle    | 2018-10-04 8:43am |
      | Cow        | 2018-10-04 8:27am |
      | Panther    | 2018-10-04 8:22am |
      | Tortoise   | 2018-10-04 8:34am |
      | Warg       | 2018-10-04 8:39am |
    And I am an anonymous user
    When I visit the solution overview page
    Then I should see the following tiles in the correct order:
      | Muffalo    |
      | Gazelle    |
      | Warg       |
      | Boomrat    |
      | Tortoise   |
      | Alpaca     |
      | Boomalope  |
      | Cow        |
      | Panther    |
      | Arctic fox |
      | Spelopede  |
      | Thrumbo    |
    And I should see the link "2"
    # Next and last page links are rendered as icons "›" and "»", but there is an
    # help text that is meant for screen readers and also visualised on mouseover.
    And I should see the link "Next page"
    And I should see the link "Last page"
    But I should not see the link "First page"
    And I should not see the link "Go to previous page"
    When I click "Next page"
    Then I should see the following tiles in the correct order:
      | Megasloth |
      | Husky     |
    And I should see the link "1"
    And I should see the link "First page"
    And I should see the link "Go to previous page"
    But I should not see the link "Next page"
    And I should not see the link "Last page"

  Scenario: View solution overview as an anonymous user
    Given the following users:
      | Username      | E-mail                            |
      | Madam Shirley | i.dont.see.the.future@example.com |
    And the following "collection" content:
      | title                 | logo     |
      | Pikachu, I choose you | logo.png |
    And the following "solution" content:
    # As of ISAICP-3618 descriptions should not be visible in regular tiles.
      | title                 | description                    | state     |
      | Non electronic health | Supports health-related fields | published |
      | Closed data           | Facilitate access to data sets | published |
      | Isolating Europe      | Reusable tools and services    | published |
      | Uniting Europe        | Unusable tools and services    | draft     |

    # Check that visiting as an anonymous does not create cache for all users.
    Given I am an anonymous user
    When I visit the solution overview page
    Then I should see the heading "Solutions"
    And I should see the text "A solution on Interoperable Europe Portal is a framework, tool, or service either hosted directly on Interoperable Europe Portal or federated from third-party repositories."
    And the page should be cacheable

    # Access the page as a moderator to ensure proper caching.
    Given I am logged in as a "moderator"
    When I visit the solution overview page
    Then I should see the heading "Solutions"
    And I should see the text "A solution on Interoperable Europe Portal is a framework, tool, or service either hosted directly on Interoperable Europe Portal or federated from third-party repositories."
    And I should see the "Non electronic health" tile
    And I should see the "Closed data" tile
    And I should see the "Isolating Europe" tile
    And I should not see the "Uniting Europe" tile
    And the page should be cacheable

    # Check page for authenticated users.
    Given I am logged in as "Madam Shirley"
    When I visit the solution overview page
    Then I should see the heading "Solutions"
    And I should see the text "A solution on Interoperable Europe Portal is a framework, tool, or service either hosted directly on Interoperable Europe Portal or federated from third-party repositories."
    Then I should see the "Non electronic health" tile
    And I should see the "Closed data" tile
    And I should see the "Isolating Europe" tile
    But I should not see the "Uniting Europe" tile
    And the page should be cacheable

    # Once more for anonymous.
    Given I am an anonymous user
    When I visit the solution overview page
    Then I should see the heading "Solutions"
    And I should see the text "A solution on Interoperable Europe Portal is a framework, tool, or service either hosted directly on Interoperable Europe Portal or federated from third-party repositories."
    And I should see the link "Non electronic health"
    And I should not see the text "Supports health-related fields"
    And I should see the link "Closed data"
    And I should not see the text "Facilitate access to data sets"
    And I should see the link "Isolating Europe"
    And I should not see the text "Reusable tools and services"
    And the page should be cacheable

    When I click "Non electronic health"
    Then I should see the heading "Non electronic health"

    # Add new solution as a moderator to directly publish it.
    And I am logged in as a moderator
    When I go to the add solution form of the "Pikachu, I choose you" collection
    And I fill in the following:
      | Title                   | Colonies in Earth                   |
      | Abstract                | Some abstract                       |
      | Description             | Some space mumbo jumbo description. |
    And I select "Demography" options from "Topic"
    And I select "Data Owner" options from "Solution type"
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Bulgarian" options from "Language"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Ambrosio Morison"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    # Create contact information.
    And I fill in "contact[form][0][title][0][value]" with "Ambrosio Morison"
    And I fill in "contact[form][0][email][value]" with "ambrosio.morison@example.com"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Publish"
    And I visit the "Colonies in Earth" solution
    And I should see the text "Colonies in Earth"

    When I visit the solution overview page
    Then I should see the text "Colonies in Earth"
    And the page should be cacheable

    # Check the new solution as an anonymous user.
    When I am an anonymous user
    When I visit the solution overview page
    Then I should see the link "Colonies in Earth"
    And the page should be cacheable

  Scenario: Users are able to filter solutions they have created or that are featured site-wide.
    Given the following users:
      | Username        | E-mail                      |
      | Marjorie Parker | marjorie.parker@example.com |
      | Ryker Brandon   | ryker.brandon@example.com   |
      | Joann Womack    | joann.womack@example.com    |
    And the following "collection" content:
      | title                 |
      | Insane Wooden Crystal |
    Given the following "solution" content:
      | title                        | collection            | featured | author          |
      | Subdivision Morbid           | Insane Wooden Crystal | yes      | Marjorie Parker |
      | Long Tungsten                | Insane Wooden Crystal | no       | Ryker Brandon   |
      | Hungry Disappointed Tungsten | Insane Wooden Crystal | yes      | Marjorie Parker |
      | Lost Yard                    | Insane Wooden Crystal | no       | Joann Womack    |
      | Lost Scattered Fish          | Insane Wooden Crystal | no       | Joann Womack    |
      | Silver Gravel                | Insane Wooden Crystal | no       | Joann Womack    |
    # Technical: use a separate step to create a collection associated to the anonymous user.
    And the following "solution" content:
      | title      | collection            | featured |
      | Flag Rough | Insane Wooden Crystal | no       |

    When I am logged in as "Joann Womack"
    And I visit the solution overview page
    Then the "My solutions content" inline facet should allow selecting the following values:
      | My solutions (3)       |
      | Featured solutions (2) |
    And the page should be cacheable
    When I click "My solutions" in the "My solutions content" inline facet
    Then I should see the following tiles in the correct order:
      | Lost Yard           |
      | Lost Scattered Fish |
      | Silver Gravel       |
    And the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
      | All solutions          |
    And the page should be cacheable
    # Regression test to ensure that the facets are cached by user.
    # Subsequent page loads of the collections page would lead to cached facets
    # to be leaked to other users.
    # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3777
    When I click "All solutions" in the "My solutions content" inline facet
    Then the "My solutions content" inline facet should allow selecting the following values:
      | My solutions (3)       |
      | Featured solutions (2) |
    And the page should be cacheable

    When I am logged in as "Ryker Brandon"
    And I visit the solution overview page
    Then the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
      | My solutions (1)       |
    And the page should be cacheable
    When I click "My solutions" in the "My solutions content" inline facet
    Then I should see the following tiles in the correct order:
      | Long Tungsten |
    And the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
      | All solutions          |
    And the page should be cacheable
    # Verify that the facets are cached for the correct user by visiting again
    # the collections page without any facet filter.
    When I click "All solutions" in the "My solutions content" inline facet
    Then the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
      | My solutions (1)       |
    And the page should be cacheable

    When I am an anonymous user
    And I visit the solution overview page
    # The anonymous user has no access to the "My solutions" facet entry.
    Then the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
    And the page should be cacheable
    When I click "Featured solutions" in the "My solutions content" inline facet
    Then I should see the following tiles in the correct order:
      | Subdivision Morbid           |
      | Hungry Disappointed Tungsten |
    And the "My solutions content" inline facet should allow selecting the following values:
      | All solutions |
    And the page should be cacheable
    When I click "All solutions" in the "My solutions content" inline facet
    Then the "My solutions content" inline facet should allow selecting the following values:
      | Featured solutions (2) |
    And the page should be cacheable

    When I am logged in as "Ryker Brandon"
    And I visit the solution overview page
    And I click "Featured solutions" in the "My solutions content" inline facet
    Then I should see the following tiles in the correct order:
      | Subdivision Morbid           |
      | Hungry Disappointed Tungsten |
    And the page should be cacheable
