@api @group-clone
Feature:
  Test ticket ISAICP-8328.

  Scenario: EIF Perspective links now appear in the quick links menu.
    Given I go to the "ABR - BRegDCAT-AP" solution
    Then I should see the link "EIF Perspective" in the "Quick links" region
    When I click "EIF Perspective"
    Then I should see the heading "EIF Perspective attributes of ABR - BRegDCAT-AP"
    And I should see the text "EIF Perspective" in the "Content" region

    # There is one solution with an EIF Perspective but a disabled link.
    Given I go to the "Big Data Test Infrastructure (BDTI)" solution
    # Use the main content region as the quick links is not rendered at all.
    Then I should not see the link "EIF Perspective" in the "Content" region
