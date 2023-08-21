@api @group-clone
Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: Anonymous visiting the landing page.
    Given I am not logged in

    Then I visit "/eu-oss-catalogue"
    When the response status code should not be 404
