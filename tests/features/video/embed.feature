@api
Feature: Embed of videos into the page.
  In order to show videos regarding my content
  As a user of the website
  I should be able to embed a restricted set of videos in the page.

  Background:
    Given collection content:
      | title                    | description                          | state     |
      | Beer brewing corporation | Beer is the real nectar of the gods. | published |

  Scenario: As a community content editor I can embed video iframes from allowed domains into the content field.
    Given I am logged in as a "facilitator" of the "Beer brewing corporation" collection
    And I go to the homepage of the "Beer brewing corporation" collection
    And I click "Add discussion" in the plus button menu

    Then I fill in the following:
      | Title | United Kingdom Brexit Notification |
    And I select "Supplier exchange" from "Topic"
    And I fill in "Content" with:
    """
    <h2>Any europa.eu and *.europa.eu are allowed.</h2>
    <iframe src="https://ec.europa.eu/avservices/play.cfm?ref=I072651&lg=EN" id="videoplayer" width="852" height="480" frameborder="0" scrolling="no" webkitAllowFullScreen="true" mozallowfullscreen="true" allowFullScreen="true"></iframe>
    <iframe src="http://europa.eu/!dV74uw" width="852" height="480" frameborder="0" scrolling="no" webkitAllowFullScreen="true" mozallowfullscreen="true" allowFullScreen="true"></iframe>
    <iframe width="600" height="340" src="https://webcast.ec.europa.eu/iprs-and-innovation-procurement-22-01-20/embed" scrolling="no" style="overflow:hidden" webkitAllowFullScreen mozAllowFullScreen allowFullScreen frameborder="0"></iframe>

    <h2>YouTube videos are allowed.</h2>
    <iframe width="560" height="315" src="https://www.youtube.com/embed/xlnYVHRp128" frameborder="0" allowfullscreen></iframe>

    <h2>Vimeo videos are allowed.</h2>
    <iframe src="https://player.vimeo.com/video/225133231" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>

    <h2>DailyMotion videos are NOT allowed (yet).</h2>
    <iframe frameborder="0" width="480" height="270" src="//www.dailymotion.com/embed/video/x5vl5l0" allowfullscreen></iframe>
    """

    Given I press "Publish"
    Then the response should contain "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//ec.europa.eu/avservices/play.cfm%3Fref%3DI072651%26lg%3DEN&amp;lang=en"
    And the response should contain "https://webtools.europa.eu/crs/iframe/?oriurl=http%3A//europa.eu/%21dV74uw&amp;lang=en"
    And the response should contain "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//webcast.ec.europa.eu/iprs-and-innovation-procurement-22-01-20/embed&amp;lang=en"
    And the response should contain "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//www.youtube.com/embed/xlnYVHRp128&amp;lang=en"
    And the response should contain "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//player.vimeo.com/video/225133231&amp;lang=en"
    And the response should not contain "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//www.dailymotion.com/embed/video/x5vl5l0&amp;lang=en"

  @javascript
  Scenario Outline: A video embed button should be shown to community content editors.
    Given I am logged in as a "facilitator" of the "Beer brewing corporation" collection
    And I go to the homepage of the "Beer brewing corporation" collection
    When I open the plus button menu
    And I click "Add discussion"
    When I fill in the following:
      | Title | Some test video |
    And I select "Supplier exchange" from "Topic"
    And I press the button "Insert Media" in the "Content" wysiwyg editor
    And a modal should open
    And I should see the following lines of text:
      | Image        |
      | File         |
      | Remote video |
    And I click "Remote video"
    And I should see the text "Allowed providers: YouTube, Vimeo."
    When I fill in "Add Remote video via URL" with "<url>"
    And I press "Add"
    Then I press "Save" in the "Modal buttons" region
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed
    And I press "Save as draft"
    And I see the "iframe" element with the "src" attribute set to "<embed url>" in the "Content" region
    And I see the "iframe" element with the "title" attribute set to "<title attribute>" in the "Content" region

    Examples:
      | url                                                                                                                                                   | embed url                                                           | title attribute                 |
      | https://www.youtube.com/watch?v=YTaLmMsaLOg                                                                                                           | /media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3DYTaLmMsaLOg | New Joinup (1/7)                |
      | https://vimeo.com/7073899                                                                                                                             | /media/oembed?url=https%3A//vimeo.com/7073899                       | Drupal Rap Video - Schipulcon09 |
