@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User is redirected from the standard collection URL.
    Given I am logged in as a "moderator"

    Then I visit "/collection/eu-oss-catalogue"
    When I should be on "/eu-oss-catalogue"
