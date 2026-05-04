@api @group-h
Feature:
  As a site builder of the site
  In order to be able to better control the structure of my content
  I need to be able to place paragraphs for content.

  Background:
    Given the following "collection" content:
      | title                 | topic |
      | Paragraphs collection | HR    |

  @javascript
  Scenario: Paragraph sections are multivalue and sort-able.
    Given the following "custom_page" content:
      | title           | collection            | topic | abstract                 |
      | Paragraphs page | Paragraphs collection | HR    | Paragraphs page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Paragraphs page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Paragraphs page" "custom page"
    When I add a "Text" paragraph in the "Custom page body" form field:
      | Body | type: ckeditor - value: AAAAAAAAAA |
    When I add a "Text" paragraph in the "Custom page body" form field:
      | Body | type: ckeditor - value: BBBBBBBBBB |
    And I press "Update"

    And I go to the "Paragraphs page" custom_page
    And I should see the following paragraphs in the given order:
      | AAAAAAAAAA |
      | BBBBBBBBBB |

    Then I open the header local tasks menu
    And I click "Edit"
    Then I "move down" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I press "Update"
    Then there should be 2 paragraphs in the page
    And I should see the following paragraphs in the given order:
      | BBBBBBBBBB |
      | AAAAAAAAAA |

    Then I open the header local tasks menu
    And I click "Edit"
    And I click "Choose component"
    And a modal should open
    Then I should not see the link "Simple paragraph"

  @javascript
  Scenario Outline: Privileged users can add paragraphs.
    Given <group type> content:
      | title                 |
      | Paragraphs group type |
    And users:
      | Username |
      | Zohan    |
      | Gilfoyle |
    And the following <group type> user membership:
      | <group type>          | user     | roles       |
      | Paragraphs group type | Zohan    | author      |
      | Paragraphs group type | Gilfoyle | facilitator |
    And the following "custom_page" content:
      | title                     | body        | <group type>          | author | state     |
      | Don't Mess with the Zohan | Wanna mess? | Paragraphs group type | Zohan  | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Don't Mess with the Zohan" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    # Author.
    When I am logged in as "Zohan"
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    And a modal should open
    Then I should see the following paragraph types in the modal in order:
      | Heading           |
      | Text              |
      | Image             |
      | Accordion         |
      | Call to action(s) |
      | Carousel          |
      | Content listing   |
      | Poll              |
      | Quote             |
      | Social media      |
      | Table of Contents |
      | Testimony         |
      | Tiles             |
      | Video             |
      | Image gallery     |

    # Facilitator.
    When I am logged in as "Gilfoyle"
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    And a modal should open
    Then I should see the following paragraph types in the modal in order:
      | Heading           |
      | Text              |
      | Image             |
      | Accordion         |
      | Call to action(s) |
      | Carousel          |
      | Content listing   |
      | Github feed       |
      | Map               |
      | Poll              |
      | Events calendar   |
      | Quote             |
      | Social media      |
      | Table of Contents |
      | Testimony         |
      | Tiles             |
      | Video             |
      | Image gallery     |

    # Moderator.
    Given I am logged in as a moderator
    And I go to the edit form of the "Don't Mess with the Zohan" "custom page"
    And I click "Choose component"
    And a modal should open
    Then I should see the following paragraph types in the modal in order:
      | Heading           |
      | Text              |
      | Image             |
      | Accordion         |
      | Call to action(s) |
      | Carousel          |
      | Block             |
      | Content listing   |
      | Entity reference  |
      | Github feed       |
      | IFrame            |
      | Map               |
      | Poll              |
      | Events calendar   |
      | Quote             |
      | Social media      |
      | Table of Contents |
      | Testimony         |
      | Tiles             |
      | Video             |
      | Webform           |
      | Image gallery     |

    Examples:
      | group type |
      | collection |
      | solution   |
