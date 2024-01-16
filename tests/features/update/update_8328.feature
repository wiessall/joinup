@api @group-clone
Feature:
  Test ticket ISAICP-8328.

  Scenario: EIF Perspective links now appear in the quick links menu.
    Given I go to the "ABR - BRegDCAT-AP" solution
    Then I should see the link "EIF Perspective" in the "Quick links" region
