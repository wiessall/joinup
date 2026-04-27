@api @group-b
Feature: Content archival.

  Scenario: All community content can be archived any time.
    Given collection content:
      # Set the moderation to yes to be able to access all states. Deletion
      # request is not available in a post moderation workflow.
      | title           | moderation |
      | Some collection | yes        |
    Given discussion content:
      | title           | collection      | body | content | changed          |
      | Test discussion | Some collection | Test | Test    | 2017-11-17 20:00 |
    When I am logged in as a moderator
    And I go to the edit form of the "Test discussion" discussion
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "Discussion Test discussion has been updated."
    And I should see the text "Archive" in the "Page title" region
    And I should see "Last update: 17/11/2017"
    When I go to the edit form of the "Test discussion" discussion
    Then I should not see the button "Archive"
    And I should see the button "Unarchive"

    When I press "Unarchive"
    Then I should see the message 'Discussion "Test discussion" has been unarchived.'
    And I should see "Last update: 17/11/2017"
    And I should not see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Test discussion" discussion
    Then I should see the button "Archive"
    And the current workflow state should be "Published"

  Scenario Outline: All community content using layout paragraphs can be archived any time.
    Given collection content:
      # Set the moderation to yes to be able to access all states. Deletion
      # request is not available in a post moderation workflow.
      | title           | moderation |
      | Some collection | yes        |
    And <type> content:
      | title       | collection      | abstract | content | headline    | changed          | field_paragraphs_body |
      | Test <type> | Some collection | Test     | Test    | Test <type> | 2019-11-12 20:00 | Some text             |

    When I am logged in as a moderator
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "<type label> Test <type> has been updated."
    And I should see the text "Archive" in the "Page title" region
    And I should see "Last update: 12/11/2019"
    When I go to the edit form of the "Test <type>" <type>
    Then I should not see the button "Archive"
    And I should see the button "Unarchive"

    When I press "Unarchive"
    Then I should see the message '<type label> "Test <type>" has been unarchived.'
    And I should see "Last update: 12/11/2019"
    And I should not see the text "Archive" in the "Page title" region
    When I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"
    And the current workflow state should be "Published"

    Examples:
      | type     | type label |
      | document | Document   |
      | event    | Event      |
      | news     | News       |

  Scenario Outline: User should not be allowed unarchiving content of an archived solution.
    Given solution content:
      | title             | state    |
      | Cartridge cleaner | archived |
    Given <type> content:
      | title                | state    | solution          |
      | Test archived <type> | archived | Cartridge cleaner |

    When I am logged in as a moderator
    And I go to the edit form of the "Test archived <type>" <type>
    Then I should not see the button "Unarchive"

    Examples:
      | type       |
      | discussion |
      | document   |
      | event      |
      | news       |

  Scenario Outline: Group facilitators can not unarchived community content
    Given <group type> content:
      # Set the moderation to yes to be able to access all states. Deletion
      # request is not available in a post moderation workflow.
      | title      | moderation |
      | Some group | yes        |
    Given discussion content:
      | title           | <group type> | body | content |
      | Test discussion | Some group   | Test | Test    |
    When I am logged in as a facilitator of the "Some group" <group type>
    And I go to the edit form of the "Test discussion" discussion
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "Discussion Test discussion has been updated."
    When I go to the edit form of the "Test discussion" discussion
    Then I should not see the button "Archive"
    And I should not see the button "Unarchive"

    Examples:
      | group type |
      | collection |
      | solution   |

  Scenario Outline: Group facilitators can not unarchived community content which is usgin layout paragraphs
    Given <group type> content:
      # Set the moderation to yes to be able to access all states. Deletion
      # request is not available in a post moderation workflow.
      | title      | moderation |
      | Some group | yes        |
    Given <type> content:
      | title       | <group type> | abstract | content | headline    | field_paragraphs_body |
      | Test <type> | Some group   | Test     | Test    | Test <type> | Test paragraph        |
    When I am logged in as a facilitator of the "Some group" <group type>
    And I go to the edit form of the "Test <type>" <type>
    Then I should see the button "Archive"

    When I press "Archive"
    Then I should see the message "<type label> Test <type> has been updated."
    When I go to the edit form of the "Test <type>" <type>
    Then I should not see the button "Archive"
    And I should not see the button "Unarchive"

    Examples:
      | group type | type     | type label |
      | collection | document | Document   |
      | collection | event    | Event      |
      | collection | news     | News       |
      | solution   | document | Document   |
      | solution   | event    | Event      |
      | solution   | news     | News       |
