@api @group-h
Feature: Video paragraph tests.

  Background:
    Given the following "collection" content:
      | title                 | state     | topic |
      | Paragraphs collection | published | HR    |

  @javascript
  Scenario: Collection facilitators can add video paragraphs.
    Given the following "custom_page" content:
      | title      | collection            | state     | abstract            |
      | Video page | Paragraphs collection | published | Video page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Video page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a facilitator of the "Paragraphs collection" collection
    And I go to the edit form of the "Video page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Video"

    # Add a video paragraph (and media).
    And the following field should be present "Remote video URL"
    And I should see the text "You can link to media from the following services: YouTube, Vimeo"
    And I press "Save" in the "Modal buttons" region
    And I should see the inline form error message "Remote video URL field is required."

    And I fill in "Remote video URL" with "http://nonsense.com"
    And I press "Save" in the "Modal buttons" region
    And I should see the inline form error message "The given URL does not match any known oEmbed providers."

    And I fill in "Remote video URL" with "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
    And I press "Save" in the "Modal buttons" region
    And the modal should be closed

    # No-one (but admins) can edit/delete media videos. This prevents unwanted
    # changes in case other pages referenced it from the media library.
    # Instead, the user can delete the video paragraph and create a new one.
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And the following field should not be present "Remote video URL"
    And I should see the text "Rick Astley - Never Gonna Give You Up (Official Video) (4K Remaster)"
    And I press "Cancel" in the "Modal buttons" region
    And the modal should be closed

    # Delete and add the same video again, this should reuse the media entity.
    # @see \Drupal\joinup_media\Plugin\Field\FieldWidget\MediaVideoUrlWidget
    And I "delete" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I click "Choose component"
    And a modal should open
    And I click "Video"
    And I fill in "Remote video URL" with "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
    And I press "Save" in the "Modal buttons" region
    And the modal should be closed

    # Save custom page, assert iframe is present.
    And I press "Update"
    And I should see the "iframe" element with the "src" attribute set to "/media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3DdQw4w9WgXcQ" in the "Content" region

    # Reference the same media video from the media library in a discussion.
    When I go to the homepage of the "Paragraphs collection" collection
    And I click "Add discussion" in the plus button menu
    And I fill in "Title" with "Video discussion"
    And I press the button "Insert Media" in the "Content" wysiwyg editor
    And a modal should open
    And I click "Remote video"
    # 2 rows: header + 1 video...
    Then the "Ventuno media library" table should have 2 rows
    And the "Ventuno media library" table should contain a column with the following data:
      | Name                                                                 |
      | Rick Astley - Never Gonna Give You Up (Official Video) (4K Remaster) |
    When I check "Rick Astley - Never Gonna Give You Up (Official Video) (4K Remaster)"
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed
    And I select "Demography" options from "Topic"
    And I press "Publish"

    # Delete the custom page.
    # Assert the media video still exists in the discussion.
    And I go to the delete form of the "Video page" "custom page"
    When I press "Delete"
    And I go to the "Video discussion" discussion
    And I should see the "iframe" element with the "src" attribute set to "/media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3DdQw4w9WgXcQ" in the "Content" region

  @javascript
  Scenario: Regression test for "cannot use the video component in layout editor of dashboards custom landing pages".
    Given I am logged in as a "moderator"
    When I am on the homepage
    Then I open the account menu
    And I click "Dashboard"
    Then I should see the heading "Dashboard"

    Then I click "Custom landing pages"
    And I click "Add custom landing page"
    And the following field should be present "URL alias"
    And I fill in the following:
      | Title    | Twelve Video Tables          |
      | Abstract | Twelve video tables abstract |
    And I select "Data gathering and processing" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Video"
    And I fill in "Remote video URL" with "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
    And I press "Save" in the "Modal buttons" region
    And the modal should be closed
    And I press "Publish"
