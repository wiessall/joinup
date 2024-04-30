@api @group-clone
Feature:
  As a user of the website
  When I go to the overview page of an EIRA term
  I want to be able to view information on the term.

  Scenario: Show related terms on the overview page.
    Given I am an anonymous user
    When I go to the "Networking Infrastructure Enablers" eira term page
    Then I should see the heading "Networking Infrastructure Enablers"
    And I should see the following links:
      | Domain Name Service     |
      | Firewall                |
      | Gateway                 |
      | Load Balancer           |
      | Private Network         |
      | Private Subnetwork      |
      | Router                  |
      | Virtual Private Network |
