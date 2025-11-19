@api @group-h
Feature:
  As a site builder of the site
  I need to be able to use "image gallery" paragraphs for content.

  Background:
    Given collection content:
      | title                    | state     |
      | Paragraphs IG collection | published |

  @javascript
  Scenario: User should be able to add image gallery paragraph.
    Given custom_page content:
      | title                        | collection               | state     | abstract            |
      | Image gallery paragraph page | Paragraphs IG collection | published | Image page abstract |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Image gallery paragraph page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a "facilitator" of the "Paragraphs IG collection" collection
    And I go to the edit form of the "Image gallery paragraph page" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Image gallery"

    When I attach the file "logo.png" to "Add a new file" in the "Modal content" region
    And I attach the file "topic.png" to "Add a new file" in the "Modal content" region
    And I attach the file "banner.jpg" to "Add a new file" in the "Modal content" region
    And I attach the file "linus.jpeg" to "Add a new file" in the "Modal content" region
    And I fill in the 1st "Alternative text" field of the "Image" file field with "Logo" in the "Modal content" region
    And I fill in the 2nd "Alternative text" field of the "Image" file field with "Topic" in the "Modal content" region
    And I fill in the 3rd "Alternative text" field of the "Image" file field with "Banner" in the "Modal content" region
    And I fill in the 4rd "Alternative text" field of the "Image" file field with "Linux" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    And I wait for CSS animations to finish
    And I wait for slick carousel ".slick--optionset--image-gallery" to be ready
    And I should have the following images on the page in this order
      | logo.png   |
      | topic.png  |
      | banner.jpg |
      | linus.jpeg |
    And I should see the following images on the page
      | logo.png  |
      | topic.png |
    And I should not see the image "banner.jpg"
    And I should not see the image "linus.jpeg"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Next']:not([aria-disabled='true'])"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Next']:not([aria-disabled='true'])"
    And I wait for CSS animations to finish
    And I should see the image "banner.jpg"
    And I should see the image "linus.jpeg"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Previous']:not([aria-disabled='true'])"
    And I wait for CSS animations to finish
    And I should see the image "banner.jpg"
    And I should not see the image "linus.jpeg"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Previous']:not([aria-disabled='true'])"
    And I wait for CSS animations to finish
    And I should not see the image "banner.jpg"
    And I should not see the image "linus.jpeg"
    And I should see a ".slick--optionset--image-gallery button[aria-label='Previous'][aria-disabled='true']" element
    Then I click on element ".slick--optionset--image-gallery .slide--0"
    And I wait until ".colorbox" element exists
    And I should see the image "logo.png"
    And I should see the button "« Prev"
    And I should see the button "Next »"
    And I should see the button "Close"
    And I press "Next »"
    And I press "Next »"
    And I press "Next »"
    And I should see the image "linus.jpeg"
    Then I press "Close"
    And I should not see the image "linus.jpeg"
