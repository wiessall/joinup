@api @group-h
Feature: Administer media.

  @javascript
  Scenario: Moderators are able to administer media.
    Given the following users:
      | Username | Roles     |
      | joe      |           |
      | theOne   | moderator |
    And the following "collection" content:
      | title |
      | Mars  |
      | Venus |
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
    And the following "Remote video" media:
      | name      | owner | video                                       | group | published |
      | The Video | joe   | https://www.youtube.com/watch?v=dQw4w9WgXcQ | Mars  | no        |

    Given I am logged in as theOne

    When I click "Library"
    Then the "media admin" table should contain the following columns:
      | Media name          | Type            | Status      | Group | Author |
      | The Collection Logo | Collection logo | Published   |       | joe    |
      | The Solution Logo   | Solution logo   | Published   |       | joe    |
      | The Event Logo      | Event logo      | Published   |       | joe    |
      | The News Logo       | News logo       | Published   |       | joe    |
      | The Image           | Image           | Published   | Mars  | joe    |
      | Other Image         | Image           | Published   | Venus | joe    |
      | The File            | File            | Published   | Mars  | joe    |
      | Other File          | File            | Published   | Venus | joe    |
      | The Video           | Remote video    | Unpublished | Mars  | joe    |

    When I type "Venus" in the "group" autocomplete field
    And I pick "Venus" from the autocomplete suggestions
    And I press "Filter"
    Then the "media admin" table should contain the following columns:
      | Media name  | Type  | Status    | Group | Author |
      | Other Image | Image | Published | Venus | joe    |
      | Other File  | File  | Published | Venus | joe    |

    When I press "Reset"
    Then the "media admin" table should contain the following columns:
      | Media name          | Type            | Status      | Group | Author |
      | The Collection Logo | Collection logo | Published   |       | joe    |
      | The Solution Logo   | Solution logo   | Published   |       | joe    |
      | The Event Logo      | Event logo      | Published   |       | joe    |
      | The News Logo       | News logo       | Published   |       | joe    |
      | The Image           | Image           | Published   | Mars  | joe    |
      | Other Image         | Image           | Published   | Venus | joe    |
      | The File            | File            | Published   | Mars  | joe    |
      | Other File          | File            | Published   | Venus | joe    |
      | The Video           | Remote video    | Unpublished | Mars  | joe    |

    When I select "Unpublished" from "Status"
    And I press "Filter"
    Then the "media admin" table should contain the following columns:
      | Media name | Type         | Status      | Group | Author |
      | The Video  | Remote video | Unpublished | Mars  | joe    |

    When I click "Add media"
    And I should see the link "Collection logo"
    And I should see the link "Event logo"
    And I should see the link "News logo"
    And I should see the link "Solution logo"
    But I should see the link "Image"
    But I should see the link "File"
    And I should see the link "Remote video"

    When I click "File"
    And I fill in "Group" with "Mars"
    And I fill in "Name" with "Just a doc"
    And I attach the file "banner.jpg" to "Add a new file"
    When I press "Save"
    Then the "media admin" table should contain the following columns:
      | Media name          | Type            | Status      | Group | Author |
      | Just a doc          | File            | Published   | Mars  | theOne |
      | The Collection Logo | Collection logo | Published   |       | joe    |
      | The Solution Logo   | Solution logo   | Published   |       | joe    |
      | The Event Logo      | Event logo      | Published   |       | joe    |
      | The News Logo       | News logo       | Published   |       | joe    |
      | The Image           | Image           | Published   | Mars  | joe    |
      | Other Image         | Image           | Published   | Venus | joe    |
      | The File            | File            | Published   | Mars  | joe    |
      | Other File          | File            | Published   | Venus | joe    |
      | The Video           | Remote video    | Unpublished | Mars  | joe    |

  # Regression test for patch resources/patch/php/drupal/core/2940605.diff
  Scenario: Ensure that multiple media are rendered without logging any errors.
    Given there are no preexisting log records on the site
    And the following "Image" media:
      | name                               | image      | alt            | group      | revision         | uuid        |
      | The Image in Shaping of disruption | simple.svg | A simple image | Portal support | 2016-07-10T13:00 | abcde-12345 |

    When I am logged in as a "facilitator" of the "Portal support" collection
    And I go to the homepage of the "Portal support" collection
    And I click "Add discussion" in the plus button menu

    Then I fill in the following:
      | Title | Sample discussion |
    And I select "Supplier exchange" options from "Topic"

    # Add in the body multiple times the media image.
    And I fill in "Content" with:
    """
    Sample discussion:
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media>
    """
    And I press "Publish"
    Then I should see the text "Sample discussion"
    And the site should not have log records with level lower than Warning

  # Regression test for the release and distribution description fields.
  # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9874
  Scenario: Media are rendered correctly for releases and distributions.
    Given the following "Image" media:
      | name                               | image      | alt            | group      | revision         | uuid        |
      | The Image in Shaping of disruption | simple.svg | A simple image | Portal support | 2016-07-10T13:00 | abcde-12345 |
    And the following "solution" content:
      | title         | description |
      | Some solution | Meh...      |
    And the following "release" content:
      | title        | release number | release notes                                                                               | is version of |
      | Some release | 0.1            | <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media> | Some solution |
    And the following "distribution" content:
      | title             | description                                                                                 | release      |
      | Some distribution | <drupal-media data-entity-type="media" data-entity-uuid="abcde-12345">&nbsp;</drupal-media> | Some release |

    When I go to the "Some release" release
    Then the response should contain "/sites/default/files/file-visibility/media/image/simple.svg"
    When I go to the "Some distribution" distribution
    Then the response should contain "/sites/default/files/file-visibility/media/image/simple.svg"
