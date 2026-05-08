@api @group-clone
Feature: Award the iec_member badge to existing active members of the IEC.

  @update:joinup_badge_deploy_201300
  Scenario: Active members of the Interoperable Europe Community are awarded the IEC member badge.
    When I am on the members page of "Interoperable Europe Community"
    And I click on element ".tile__link"
    Then I should see the text "Interoperable Europe Community member" in the "Header" region
