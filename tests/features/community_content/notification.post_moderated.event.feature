@api @group-b
Feature: Notification test for the event transitions on a post moderated parent.
  In order to manage my collections
  As an owner of the collection
  I want to receive a notification when an entity is proposed.

  Scenario Outline: Notifications should be sent whenever a event is going through a relevant transition.
    Given users:
      | Username         | Roles     | E-mail                      | First name | Family name |
      | Notify moderator | moderator | notify_moderator@test.com   | Notify     | Moderator   |
      | CC owner         |           | notify_owner@test.com       | CC         | Owner       |
      | CC facilitator   |           | notify_facilitator@test.com | CC         | Facilitator |
      | CC member        |           | notify_member@test.com      | CC         | Member      |
    And the following "collection" content:
      | title              | content_creation | group_moderation |
      | CC post collection | members          | <moderation>     |
    And the following collection user memberships:
      | collection         | user           | roles       |
      | CC post collection | CC owner       | owner       |
      | CC post collection | CC facilitator | facilitator |
      | CC post collection | CC member      | <roles>     |
    And the following "event" content:
      | title                                | author    | abstract | field_paragraphs_body | location                       | collection         | field_state  | created    |
      | CC notify post publish               | CC member | abstract | body                  | Half Moon Street, London       | CC post collection | draft        | now -2 day |
      | CC notify post request changes       | CC member | abstract | body                  | Quai d'Orleans, Paris          | CC post collection | published    | now -2 day |
      | CC notify post report                | CC member | abstract | body                  | Rue du Mont Thabor, Paris      | CC post collection | published    | now -2 day |
      | CC notify post propose from reported | CC member | abstract | body                  | Cort Adelers Gate, Oslo        | CC post collection | needs_update | now -2 day |
      | CC notify post approve proposed      | CC member | abstract | body                  | Ferdinand Bolstraat, Amsterdam | CC post collection | proposed     | now -2 day |
      | CC notify post delete                | CC member | abstract | body                  | Lychener Strasse, Berlin       | CC post collection | published    | now -2 day |

    # Test 'create' operation.
    When I am logged in as "CC member"
    And I go to the "CC post collection" collection
    And I click "Add event" in the plus button menu
    And I fill in "Title" with "CC notify create publish"
    And I fill in "Abstract" with "This is a test event"
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | CC notify create publish |
      | field_body:format | content_editor           |
    And I fill in "Physical location" with "Capel Street, Dublin"
    And I select "Statistics and Analysis" options from "Topic"
    And I press "Publish"
    Then the following email should have been sent:
      | recipient | CC owner                                                                                                                                                               |
      | subject   | Interoperable Europe Portal: Content has been published                                                                                                                |
      | body      | CC Member has published the new event - "CC notify create publish" in the collection: "CC post collection". You can access the new content at the following link: http |

    # Test 'update' operation.
    When I am logged in as "CC member"
    And I go to the edit form of the "CC notify post publish" event
    And I press "Publish"
    Then the following email should have been sent:
      | recipient | CC owner                                                                                                  |
      | subject   | Interoperable Europe Portal: Content has been published                                                   |
      | body      | CC Member has published the new event - "CC notify post publish" in the collection: "CC post collection". |

    When I am logged in as "CC facilitator"
    And I go to the edit form of the "CC notify post request changes" event
    And I press "Request changes"
    Then I should see the inline form error message "This action requires you to fill in the motivation field"
    When I fill in "Motivation" with "Can you do some changes?"
    And I press "Request changes"
    Then the following email should have been sent:
      | recipient | CC member                                                                                                                                                                                                    |
      | subject   | Interoperable Europe Portal: Content has been updated                                                                                                                                                        |
      | body      | the Facilitator, CC Facilitator has requested you to modify the event - "CC notify post request changes" in the collection: "CC post collection", with the following motivation: "Can you do some changes?". |

    When I am logged in as "CC facilitator"
    And I go to the edit form of the "CC notify post report" event
    And I press "Report"
    Then I should see the inline form error message "This action requires you to fill in the motivation field"
    When I fill in "Motivation" with "Your content is reported"
    And I press "Request changes"
    Then the following email should have been sent:
      | recipient | CC member                                                                                                                                                                                           |
      | subject   | Interoperable Europe Portal: Content has been updated                                                                                                                                               |
      | body      | the Facilitator, CC Facilitator has requested you to modify the event - "CC notify post report" in the collection: "CC post collection", with the following motivation: "Your content is reported". |

    When I am logged in as "CC facilitator"
    And I go to the edit form of the "CC notify post approve proposed" event
    And I press "Publish"
    Then the following email should have been sent:
      | recipient | CC member                                                                                                                                                          |
      | subject   | Interoperable Europe Portal: Content has been updated                                                                                                              |
      | body      | the Facilitator, CC Facilitator has approved your request of publication of the event - "CC notify post approve proposed" in the collection: "CC post collection". |

    # Test 'delete' operation.
    When I am logged in as "CC facilitator"
    And I go to the edit form of the "CC notify post delete" event
    And I click "Delete"
    And I press "Delete"
    Then the following email should have been sent:
      | recipient | CC member                                                                                                           |
      | subject   | Interoperable Europe Portal: Content has been deleted                                                               |
      | body      | Facilitator CC Facilitator has deleted the event - "CC notify post delete" in the collection: "CC post collection". |

    Examples:
      | moderation | roles  |
      | no         |        |
      | yes        | author |
