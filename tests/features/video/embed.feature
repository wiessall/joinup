@api
Feature: Embed of videos into the page.
  In order to show videos regarding my content
  As a user of the website
  I should be able to embed a restricted set of videos in the page.

  Background:
    Given the following collection:
      | title       | Beer brewing corporation             |
      | description | Beer is the real nectar of the gods. |
      | state       | published                            |

  Scenario: As a community content editor I can embed video iframes from allowed providers into the content field.
    Given I am logged in as a "facilitator" of the "Beer brewing corporation" collection
    And I go to the homepage of the "Beer brewing corporation" collection
    And I click "Add discussion" in the plus button menu

    Then I fill in the following:
      | Title | United Kingdom Brexit Notification |
    And I select "Supplier exchange" from "Topic"
    And I fill in "Content" with:
    """
    <h2>All below videos have 'autoplay' set to TRUE</h2>
    European Commission videos are allowed.
    <iframe src="https://ec.europa.eu/avservices/play.cfm?ref=I072651&lg=EN&starttime=0&autostart=true" id="videoplayer" width="852" height="480" frameborder="0" scrolling="no" webkitAllowFullScreen="true" mozallowfullscreen="true" allowFullScreen="true"></iframe>
    European Commission videos are allowed.
    <iframe src="https://ec.europa.eu/avservices/play.cfm?ref=I-087075&lg=EN&starttime=0&autostart=true" id="videoplayer" width="852" height="480" frameborder="0" scrolling="no" webkitAllowFullScreen="true" mozallowfullscreen="true" allowFullScreen="true"></iframe>
    European Commission videos (with short URL that will be resolved) are allowed.
    <iframe src="http://europa.eu/!dV74uw" width="852" height="480" frameborder="0" scrolling="no" webkitAllowFullScreen="true" mozallowfullscreen="true" allowFullScreen="true"></iframe>
    YouTube videos are allowed.
    <iframe width="560" height="315" src="https://www.youtube.com/embed/xlnYVHRp128?autoplay=1" frameborder="0" allowfullscreen></iframe>
    Vimeo videos are allowed.
    <iframe src="https://player.vimeo.com/video/225133231?autoplay=1" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
    DailyMotion videos are NOT allowed (yet).
    <iframe frameborder="0" width="480" height="270" src="//www.dailymotion.com/embed/video/x5vl5l0?autoPlay=1" allowfullscreen></iframe>
    European Commission Videos from webcast are allowed.
    <iframe width="600" height="340" src="https://webcast.ec.europa.eu/iprs-and-innovation-procurement-22-01-20/embed" scrolling="no" style="overflow:hidden" webkitAllowFullScreen mozAllowFullScreen allowFullScreen frameborder="0"></iframe>
    """

    Given I press "Publish"
    # All allowed videos have now the autoplay set to FALSE.
    Then the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=%2F%2Faudiovisual.ec.europa.eu%2Fembed%2Findex.html%3Fref%3DI-072651%26lg%3DEN%26starttime%3D0%26autoplay%3Dfalse"
    And the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=%2F%2Faudiovisual.ec.europa.eu%2Fembed%2Findex.html%3Fref%3DI-087075%26lg%3Den%26starttime%3D0%26autoplay%3Dfalse"
    And the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=%2F%2Faudiovisual.ec.europa.eu%2Fembed%2Findex.html%3Fref%3DI-136289%26lg%3Den%26starttime%3D0%26autoplay%3Dfalse"
    And the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=https%3A%2F%2Fwww.youtube-nocookie.com%2Fembed%2FxlnYVHRp128%3Fautoplay%3D0%26start%3D0%26rel%3D0"
    And the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=https%3A%2F%2Fplayer.vimeo.com%2Fvideo%2F225133231"
    And the response should not contain "//europa.eu/webtools/crs/iframe/?oriurl=%2F%2Fwww.dailymotion.com%2Fembed%2Fvideo%2Fx5vl5l0"
    But the response should contain "//europa.eu/webtools/crs/iframe/?oriurl=%2F%2Fwebcast.ec.europa.eu%2Fiprs-and-innovation-procurement-22-01-20%2Fembed%3Flg%3Den%26starttime%3D0%26autostart%3Dfalse"

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
      | Image |
      | File  |
      | Video |
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
      | url                                                                                                                                                   | embed url                                                                                                                                         | title attribute  |
      | https://www.youtube.com/watch?v=YTaLmMsaLOg                                                                                                           | /media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3DYTaLmMsaLOg | New Joinup (1/7)                |
      | https://vimeo.com/7073899                                                                                                                             | /media/oembed?url=https%3A//vimeo.com/7073899                       | Drupal Rap Video - Schipulcon09 |
