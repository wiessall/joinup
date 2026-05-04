@api @group-b
Feature: Sharing content between collections
  As a privileged user
  I want to share content between collections
  So that useful information has more visibility

  Scenario: Sharing of event and news expires by time
    Given the following "collection" content:
      | title        |
      | Target group |
    And the following "news" content:
      | title       | collection     | shared on    | shared at |
      | Shared news | Portal support | Target group | - 32 days |
    And the following "event" content:
      | title        | collection     | shared on    | shared at |
      | Shared event | Portal support | Target group | -7 months |

    When I go to the "Target group" collection
    Then I should see the "Shared event" tile
    And I should see the "Shared news" tile
    Then I run the "joinup_group" cron
    And I run the "joinup_group_expired_sharing" queue worker
    And I go to the "Target group" collection
    Then I should not see the "Shared event" tile
    And I should not see the "Shared news" tile

  @javascript
  Scenario Outline: Users can share content in the groups they are member of.
    Given the following users:
      | Username    | E-mail                  |
      | Sara Barber | sara.barber@example.com |
    And the following "<group type>" content:
      | title        |
      | Classic Rock |
      | Hip-Hop      |
      | Power ballad |
      | Drum'n'Bass  |
    And the following "document" content:
      | title                       | <group type> |
      | Rockabilly is still rocking | Classic Rock |
    And the following <group type> user memberships:
      | <group type> | user        | roles |
      | Classic Rock | Sara Barber |       |
      | Hip-Hop      | Sara Barber |       |
      | Drum'n'Bass  | Sara Barber |       |
    And the following "<content type>" content:
      | title               | <group type> |
      | Interesting content | Hip-Hop      |

    # Anonymous users can share only in social networks.
    When I am an anonymous user
    And I visit the "Interesting content" "<content type>"
    And I click "Share"
    Then a modal should open
    And the following fields should not be present "Classic Rock, Hip-Hop, Power ballad, Drum'n'Bass"

    # This "authenticated user" is not member of any collections or solution, so they can
    # share only in social networks.
    When I am logged in as an "authenticated user"
    And I visit the "Interesting content" "<content type>"
    And I click "Share"
    Then a modal should open
    And the following fields should not be present "Classic Rock, Hip-Hop, Power ballad, Drum'n'Bass"

    # A member of a single collection or solution which is the one where the content was
    # created can share on social networks only.
    When I am logged in as a member of the "Hip-Hop" <group type>
    And I visit the "Interesting content" "<content type>"
    And I click "Share"
    Then a modal should open
    And the following fields should not be present "Classic Rock, Hip-Hop, Power ballad, Drum'n'Bass"

    # A group member should see the link.
    When I am logged in as "Sara Barber"
    And I go to the "Rockabilly is still rocking" document
    Then I should see the heading "Rockabilly is still rocking"
    When I click "Share"
    Then a modal should open
    And I should see the following lines of text:
      | Share on                                    |
      | Facebook                                    |
      | X                                           |
      | Linkedin                                    |
      | Other groups on Interoperable Europe Portal |
    # The groups the user is member of should be available.
    And the following fields should be present "Hip-Hop, Drum'n'Bass"
    # The parent group and groups the user is not a member of, should not be shown.
    But the following fields should not be present "Classic Rock, Power ballad"

    # Verify on another node the correctness of the share tool.
    When I visit the "Interesting content" "<content type>"
    And I click "Share"
    Then a modal should open
    And the following fields should be present "Classic Rock, Drum'n'Bass"
    And the following fields should not be present "Hip-Hop, Power ballad"

    # Share the content in a group.
    Given I mark all emails as read
    When I check "Classic Rock"
    And I press "Share" in the "Modal buttons" region
    Then I should see the success message "Item was shared on the following groups: Classic Rock."
    Then 0 e-mails should have been sent
    # Verify that the groups where the content has already been shared are not shown anymore in the list.
    When I click "Share"
    Then a modal should open
    Then the following fields should be present "Drum'n'Bass"
    And the following fields should not be present "Classic Rock, Hip-Hop, Power ballad"

    # The shared content should be shown amongst the other content tiles.
    When I go to the homepage of the "Classic Rock" <group type>
    Then the page should show only the tiles "Rockabilly is still rocking, Interesting content"
    And the "Interesting content" tile should be marked as shared from "Hip-Hop"
    When I visit the content overview page
    Then the "Interesting content" tile should not be marked as shared
    # It should not be shared on the other group.
    When I go to the homepage of the "Drum'n'Bass" <group type>
    Then I should not see the "Interesting content" tile

    # Content can be un-shared only by facilitators of the group they are shared on.
    When I am an anonymous user
    And I go to the homepage of the "Classic Rock" <group type>
    Then I should see the "Interesting content" tile
    And I should not see the contextual link "Unshare" in the "Interesting content" tile

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Classic Rock" <group type>
    Then I should see the "Interesting content" tile
    And I should not see the contextual link "Unshare" in the "Interesting content" tile

    When I am logged in as a facilitator of the "Power ballad" <group type>
    And I go to the homepage of the "Classic Rock" <group type>
    Then I should see the "Interesting content" tile
    And I should not see the contextual link "Unshare" in the "Interesting content" tile

    When I am logged in as a facilitator of the "Classic Rock" <group type>
    And I go to the homepage of the "Classic Rock" <group type>
    Then I should see the "Interesting content" tile
    And I should see the contextual link "Unshare" in the "Interesting content" tile
    When I click the contextual link "Unshare" in the "Interesting content" tile
    Then a modal will open
    And I should see the text "Unshare Interesting content from"
    Then the following fields should be present "Classic Rock"
    And the following fields should not be present "Drum'n'Bass, Hip-Hop, Power ballad"

    # Unshare the content.
    When I check "Classic Rock"
    And I press "Submit" in the "Modal buttons" region
    And the modal should be closed

    # I should still be on the same page, but the group content should be
    # changed. The "Interesting content" should no longer be visible.
    Then I should see the success message "Item was unshared from the following groups: Classic Rock."
    Then 0 e-mails should have been sent
    And the page should show only the tiles "Rockabilly is still rocking"

    # Verify that the content is again shareable.
    When I visit the "Interesting content" "<content type>"
    When I click "Share"
    Then a modal should open
    And the following fields should be present "Classic Rock"
    And the following fields should not be present "Drum'n'Bass, Hip-Hop, Power ballad"

    # Verify that the unshare link is not present when the content is not
    # shared anywhere.
    When I go to the homepage of the "Hip-Hop" <group type>
    Then I should see the "Interesting content" tile
    And the "Interesting content" tile should not be marked as shared
    And I should not see the contextual link "Unshare" in the "Interesting content" tile

    # The content should obviously not shared on the other group too.
    When I go to the homepage of the "Drum'n'Bass" <group type>
    Then I should not see the "Interesting content" tile

    Examples:
      | content type | group type |
      | event        | collection |
      | event        | solution   |
      | document     | collection |
      | document     | solution   |
      | news         | collection |
      | news         | solution   |

  @javascript
  Scenario Outline: Share/Unshare should be visible according to the group permissions.
    Given the following "<group type>" content:
      | title      |
      | Westeros   |
      | Essos city |
    And "<content type>" content:
      | title       | <group type> |
      | Iron throne | Westeros     |
    Given the following users:
      | Username       | E-mail                     | Roles     |
      | Jamie Lanister | jamie.lanister@example.com | moderator |
      | John Snow      | john.snow@example.com      |           |
      | Arya Stark     | arya.stark@example.com     |           |
    And the following <group type> user memberships:
      | <group type> | user       | roles       |
      | Westeros     | John snow  | facilitator |
      | Essos city   | John snow  | member      |
      | Essos city   | Arya Stark | facilitator |

    When I am logged in as "Arya Stark"
    And I visit the content overview page
    Then I should see the contextual link "Share" in the "Iron throne" tile
    And I should not see the contextual link "Unshare" in the "Iron throne" tile

    When I am logged in as "John Snow"
    And I visit the content overview page
    Then I should see the contextual link "Share" in the "Iron throne" tile
    But I should not see the contextual link "Unshare" in the "Iron throne" tile

    When I click the contextual link "Share" in the "Iron throne" tile
    Then a modal should open
    And the following fields should be present "Essos city"
    And I should see "<notification>"
    When I check "Essos city"
    And I press "Share" in the "Modal buttons" region
    Then I should see the success message "Item was shared on the following groups: Essos city."

    When I visit the content overview page
    Then I should see the contextual link "Share" in the "Iron throne" tile
    # Simple members can still not unshare content from any group.
    But I should not see the contextual link "Unshare" in the "Iron throne" tile

    When I am logged in as "Arya Stark"
    And I visit the content overview page
    # Link 'Unshare' vary by user og role since the 2 users up to now have the same permissions outside og.
    Then I should see the contextual links "Share, Unshare" in the "Iron throne" tile
    When I click the contextual link "Unshare" in the "Iron throne" tile
    Then a modal should open
    And the following fields should be present "Essos city"

    When I am logged in as "Jamie Lanister"
    And I visit the content overview page
    Then I should see the contextual link "Share" in the "Iron throne" tile
    # Moderators should be able to unshare from every group the content is shared on.
    And I should see the contextual link "Unshare" in the "Iron throne" tile
    When I click the contextual link "Unshare" in the "Iron throne" tile
    Then a modal should open
    And the following fields should be present "Essos city"

    Examples:
      | content type | group type | notification                                                                                                                                                                                                                            |
      | event        | collection | Please note: You must be subscribed to the target space to share your content. Your event will be shared on that space for six months. After that, it will be considered obsolete and will be automatically removed from that space.    |
      | event        | solution   | Please note: You must be subscribed to the target space to share your content. Your event will be shared on that space for six months. After that, it will be considered obsolete and will be automatically removed from that space.    |
      | document     | collection |                                                                                                                                                                                                                                         |
      | document     | solution   |                                                                                                                                                                                                                                         |
      | discussion   | collection |                                                                                                                                                                                                                                         |
      | discussion   | solution   |                                                                                                                                                                                                                                         |
      | news         | collection | Please note: You must be subscribed to the target space to share your content. Your news item will be shared on that space for one month. After that, it will be considered obsolete and will be automatically removed from that space. |
      | news         | solution   | Please note: You must be subscribed to the target space to share your content. Your news item will be shared on that space for one month. After that, it will be considered obsolete and will be automatically removed from that space. |

  Scenario: Shared pinned content is erroneously shown first.
    Given the following "collection" content:
      | title         |
      | Chocolate Way |
    And the following "document" content:
      | title                 | collection     | shared on     | created    | pinned |
      | Milky Way content     | Portal support | Chocolate Way | 2017-06-04 | yes    |
      | Chocolate Way content | Chocolate Way  |               | 2017-06-05 | no     |
    When I go to the homepage of the "Chocolate Way" collection
    Then I should see the following tiles in the correct order:
      | Chocolate Way content |
      | Milky Way content     |

  @javascript
  Scenario: Carousel tiles should show the "Shared from" visual cue.
    Given the following "collection" content:
      | title |
      | Earth |
      | Mars  |
      | Venus |
    And the following "news" content:
      | title         | collection | shared on   |
      | Earth content | Earth      | Mars        |
      | Mars content  | Mars       |             |
      | Venus content | Venus      | Earth, Mars |

    When I am logged in as a moderator
    And I go to the homepage of the "Earth" collection
    And I click "Add custom page" in the plus button menu

    Then I fill in the following:
      | Title | News carousel |
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout | one column |
    And I click "Choose component"
    And a modal should open
    And I click "Carousel"
    And I check "Global search"
    And I check "Allow shared content"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"

    Then I should see the "Earth content" tile
    And I should see the "Mars content" tile
    And the "Mars content" tile should be marked as shared from "Mars"
    And I should see the "Venus content" tile
    And the "Venus content" tile should be marked as shared from "Venus"
