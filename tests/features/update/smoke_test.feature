@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Visit Joinup collection.

    Given I am an anonymous user
    When I visit "/collection/joinup"
    Then I should see the link "Federation of solutions"

    # Check that the Solr index has been restored.
    When I visit "/search?keys=joinup&f[0]=type:collection"
    Then I should see "The platform Joinup is the European Commission's one-stop shop for interoperable, open and free digital government ICT solutions"

  Scenario: When I am on target page overview and I try edit then I should not see errors.
    Given I am logged in as a moderator
    When I visit "/collection/semic-support-centre/solution/livestream-tv-nederland-verenigde-staten-live-op-tv-en-livestream-03-december-2022"
    Then I should see the heading "(Livestream-tv==) ] Nederland-Verenigde Staten live op tv en livestream 03 december 2022"
    And I click "Edit" in the "Entity actions" region
    Then I should see the heading "Edit Solution (Livestream-tv==) ] Nederland-Verenigde Staten live op tv en livestream 03 december 2022"
