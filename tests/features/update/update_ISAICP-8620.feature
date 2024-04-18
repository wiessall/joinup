@api @group-clone
Feature: Access to the group edit form is allowed even if content creators is
  empty.

  Scenario: Regression test content creators
    Given I am logged in as a moderator
    And I go to "/collection/digital-skills-public-sector/solution/iop-eiradive/edit"
    Then the response status code should be 200
