@api @group-clone
Feature: Test for ISAICP-10838

  Scenario: Test for ISAICP-10838
    Given I go to "/collection/semic-support-centre/solution/dcat-application-profile-data-portals-europe/eif-perspective"
    Then I should see "Name of the solution: DCAT Application Profile for data portals in Europe"
    And the page should contain the html text 'src="/sites/default/files/solution/logo/2024-05/DCAT-AP.svg"'
