@api @group-h
Feature: Administer media.

  @uploadFiles:banner.jpg @javascript
  Scenario: Moderators are able to administer media.
    Given users:
      | Username | Roles     |
      | joe      |           |
      | theOne   | moderator |
    And collections:
      | title | state     |
      | Mars  | published |
      | Venus | published |
    And the following "Collection logo" media:
      | name                | owner | image                       |
      | The Collection Logo | joe   | library/collection-logo.png |
    And the following "Solution logo" media:
      | name              | owner | image                     |
      | The Solution Logo | joe   | library/solution-logo.png |
    And the following "Event logo" media:
      | name           | owner | image                  |
      | The Event Logo | joe   | library/event-logo.png |
    And the following "News logo" media:
      | name          | owner | image                 |
      | The News Logo | joe   | library/news-logo.png |
    And the following "Image" media:
      | name        | owner | image       | alt            | group |
      | The Image   | joe   | simple.svg  | A simple image | Mars  |
      | Other Image | joe   | richard.jpg | Long Live FOSS | Venus |
    And the following "File" media:
      | name       | owner | file     | group |
      | The File   | joe   | text.pdf | Mars  |
      | Other File | joe   | test.zip | Venus |
    And the following "Video" media:
      | name      | owner | video                                       | group | published |
      | The Video | joe   | https://www.youtube.com/watch?v=dQw4w9WgXcQ | Mars  | no        |

    Given I am logged in as theOne

    When I click "Library"
    Then the "media admin" table should contain the following columns:
      | Media name            | Type              | Status      | Group | Author |
      | The Collection Logo   | Collection logo   | Published   |       | joe    |
      | The Solution Logo     | Solution logo     | Published   |       | joe    |
      | The Event Logo        | Event logo        | Published   |       | joe    |
      | The News Logo         | News logo         | Published   |       | joe    |
      | The Image             | Image             | Published   | Mars  | joe    |
      | Other Image           | Image             | Published   | Venus | joe    |
      | The File              | File              | Published   | Mars  | joe    |
      | Other File            | File              | Published   | Venus | joe    |
      | The Video             | Video             | Unpublished | Mars  | joe    |

    When I type "Venus" in the "group" autocomplete field
    Then I wait until the page contains the text "Venus"
    And I pick "Venus" from the autocomplete suggestions
    And I press "Filter"
    Then the "media admin" table should contain the following columns:
      | Media name  | Type  | Status    | Group | Author |
      | Other Image | Image | Published | Venus | joe    |
      | Other File  | File  | Published | Venus | joe    |

    When I press "Reset"
    Then the "media admin" table should contain the following columns:
      | Media name            | Type              | Status      | Group | Author |
      | The Collection Logo   | Collection logo   | Published   |       | joe    |
      | The Solution Logo     | Solution logo     | Published   |       | joe    |
      | The Event Logo        | Event logo        | Published   |       | joe    |
      | The News Logo         | News logo         | Published   |       | joe    |
      | The Image             | Image             | Published   | Mars  | joe    |
      | Other Image           | Image             | Published   | Venus | joe    |
      | The File              | File              | Published   | Mars  | joe    |
      | Other File            | File              | Published   | Venus | joe    |
      | The Video             | Video             | Unpublished | Mars  | joe    |

    When I select "Unpublished" from "Status"
    And I press "Filter"
    Then the "media admin" table should contain the following columns:
      | Media name | Type  | Status      | Group | Author |
      | The Video  | Video | Unpublished | Mars  | joe    |

    When I click "Add media"
    And I should see the link "Collection logo"
    And I should see the link "Event logo"
    And I should see the link "News logo"
    And I should see the link "Solution logo"
    But I should see the link "Image"
    But I should see the link "File"
    And I should see the link "Video"

    When I click "File"
    And I fill in "Group" with "Mars"
    And I fill in "Name" with "Just a doc"
    And I attach the file "banner.jpg" to "Add a new file"
    When I press "Save"
    Then the "media admin" table should contain the following columns:
      | Media name            | Type              | Status      | Group | Author |
      | Just a doc            | File              | Published   | Mars  | theOne |
      | The Collection Logo   | Collection logo   | Published   |       | joe    |
      | The Solution Logo     | Solution logo     | Published   |       | joe    |
      | The Event Logo        | Event logo        | Published   |       | joe    |
      | The News Logo         | News logo         | Published   |       | joe    |
      | The Image             | Image             | Published   | Mars  | joe    |
      | Other Image           | Image             | Published   | Venus | joe    |
      | The File              | File              | Published   | Mars  | joe    |
      | Other File            | File              | Published   | Venus | joe    |
      | The Video             | Video             | Unpublished | Mars  | joe    |
