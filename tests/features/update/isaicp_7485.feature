@api @group-clone
Feature: Error is raised when trying to edit/delete 'spam' solution page.
  Scenario: When I am on target page overview and I try edit then I should not see errors.
    Given I am logged in as a moderator
    When I visit "/collection/semic-support-centre/solution/livestream-tv-nederland-verenigde-staten-live-op-tv-en-livestream-03-december-2022"
    Then I should see the heading "(Livestream-tv==) ] Nederland-Verenigde Staten live op tv en livestream 03 december 2022"
    And I click "Edit" in the "Entity actions" region
    Then I should see the heading "Edit Solution (Livestream-tv==) ] Nederland-Verenigde Staten live op tv en livestream 03 december 2022"

