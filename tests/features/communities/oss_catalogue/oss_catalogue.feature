@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User is redirected from the standard collection URL.
    Given I visit "/collection/eu-oss-catalogue"
    Then I should be on "/eu-oss-catalogue"

  Scenario: User cannot access OSS Catalogue's Page Manager page
    # We assume page's path is '/eu-oss-catalogue-dummy'
    When I go to "/eu-oss-catalogue-dummy"
    Then the response status code should be 404

  @javascript
  Scenario: User sees the right content
    When I visit "/eu-oss-catalogue"
    And I should see the following headings:
      | Discover our initiative |
    And I should see the text "European Open-Source Software Solutions Catalogue"

    When I click "advanced search"
    And I should see the heading "Search in European Open-source Software Solutions Catalogue"
