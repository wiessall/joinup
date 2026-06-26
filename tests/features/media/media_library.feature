@api @group-h
Feature: Media library

  Background:
    Given the following "Image" media:
      | name         | image       | alt            | group                              |
      | Portal Image | tim.jpg     | TBL            | Portal support                     |
      | IOP Image    | richard.jpg | Long Live FOSS | Interoperability assessments space |
      | Global Image | linus.jpeg  | Lt             |                                    |

  @javascript
  Scenario: Media library reveals only global media if there is no group context
    Given the following webforms:
      | id           | title | elements                                        |
      | test_webform | Test  | [{"#type": "processed_text", "#title": "Test"}] |
    And I am logged in as a moderator

    When I visit "/admin/structure/webform/manage/test_webform/element/0/edit"
    And I press the button "Insert Media" in the "Processed text settings" wysiwyg editor
    Then a modal should open
    And I should see "Global Image"
    But I should not see "Portal Image"
    And I should not see "IOP Image"

  @javascript
  Scenario: Media library reveals items belonging to the current group only.
    Given I am logged in as a facilitator of the "Portal support" collection
    When I go to the edit form of the "Portal support" collection
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open
    But I should see "Portal Image"
    And I should not see "Global Image"
    And I should not see "IOP Image"
