@api @group-d
Feature: Input filter
  In order to maintain security
  As a user
  The HTML I can use in the WYSIWYG editor gets filtered

  Background:
    Given collection content:
      | title         | logo     |
      | Netflix group | logo.png |

  Scenario: Ensure all required formats are supported in the content editor.
    Given the following "Image" media:
      | name         | image    | alt                  | group         | uuid          |
      | Inline image | logo.png | Image media alt text | Netflix group | whatever uuid |
    And discussion content:
      | title                              | body                                                                                                                                                                                                                                                                                | collection    |
      | Jessica Jones returns              | <iframe width="560" height="315" src="https://www.youtube.com/embed/nWHUjuJ8zxE" frameborder="0" allowfullscreen></iframe>                                                                                                                                                          | Netflix group |
      | Luke cage                          | <iframe width="50" height="50" src="https://www.example.com" ></iframe>                                                                                                                                                                                                             | Netflix group |
      | Slideshare presentation            | <iframe src="//www.slideshare.net/slideshow/embed_code/key/hJ3x3pTrtGaatQ" width="595" height="485" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" style="border:1px solid #CCC; border-width:1px; margin-bottom:5px; max-width: 100%;" allowfullscreen> </iframe> | Netflix group |
      | Google docs                        | <iframe frameborder="0" height="800" marginheight="0" marginwidth="0" src="https://docs.google.com/forms/d/1dBGzMp9whY2Ibxf4pUQNadpE2C3ywxdDefSSM3BdwJ4/viewform?embedded=true" width="100%">Loading...</iframe>                                                                    | Netflix group |
      | Interoperable Europe Portal iframe | <iframe frameborder="0" height="800" marginheight="0" marginwidth="0" src="/homepage" width="100%"></iframe>                                                                                                                                                                        | Netflix group |
      | Quoted texts                       | <q>This is a famous quote.</q> ~ Interoperable Europe Portal developer.                                                                                                                                                                                                             | Netflix group |
      # Since area coordinates contains commas and we don't want this to be interpreted as multiple values we need to enclose the value with double quotes and escape the quotes in the HTML.
      | Image map                          | "<map name=""imagemap""><area shape=""circle"" coords=""50,50,25"" href=""/"" alt=""Homepage""/></map><img usemap=""imagemap"" src=""/images/my-image.png"" alt=""Imagemap example"" />"                                                                                            | Netflix group |
      | Accessibility planning             | Accessibility planning is framed in the context of social exclusion within transport planning <img src="/web/sites/default/files/logo.png" />                                                                                                                                       | Netflix group |
      | Accessibility rating               | The Accessibility Rating Matrix (ARM) <drupal-media data-entity-type="media" data-entity-uuid="whatever uuid" />                                                                                                                                                                    | Netflix group |

    # 'src' attributes are the url encoded version of the URLs that were inputted prepended by the ec cck url.
    When I go to the "Jessica Jones returns" discussion
    Then I see the "iframe" element with the "src" attribute set to "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//www.youtube.com/embed/nWHUjuJ8zxE&lang=en" in the "Content" region
    When I go to the "Slideshare presentation" discussion
    Then I see the "iframe" element with the "src" attribute set to "https://webtools.europa.eu/crs/iframe/?oriurl=//www.slideshare.net/slideshow/embed_code/key/hJ3x3pTrtGaatQ&lang=en" in the "Content" region
    When I go to the "Google docs" discussion
    Then I see the "iframe" element with the "src" attribute set to "https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//docs.google.com/forms/d/1dBGzMp9whY2Ibxf4pUQNadpE2C3ywxdDefSSM3BdwJ4/viewform%3Fembedded%3Dtrue&lang=en" in the "Content" region
    When I go to the "Interoperable Europe Portal iframe" discussion
    # Local urls are not prone to the external cookie consent check.
    Then I see the "iframe" element with the "src" attribute set to "/homepage" in the "Content" region
    When I go to the "Luke cage" discussion
    Then I should not see the "iframe" element with the "src" attribute set to "https://www.example.com" in the "Content" region
    When I go to the "Quoted texts" discussion
    Then the response should contain "<q>This is a famous quote.</q> ~ Interoperable Europe Portal developer."
    When I go to the "Image map" discussion
    Then I see the "map" element with the "name" attribute set to "imagemap" in the "Content" region
    And I see the "img" element with the "usemap" attribute set to "imagemap" in the "Content" region
    And the response should contain "<area shape=\"circle\" coords=\"50,50,25\" href=\"/\" alt=\"Homepage\">"
    When I go to the "Accessibility planning" discussion
    Then I see the "img" element with empty "alt" attribute in the "Content" region
    When I go to the "Accessibility rating" discussion
    Then I see the "img" element with the "alt" attribute set to "Image media alt text" in the "Content" region

    Given I am logged in as an authenticated
    When I go to the "Jessica Jones returns" discussion
    And I fill in "Create comment" with "<q>Quoted</q>"
    When I press "Post comment"
    Then the page should contain the html text "<q>Quoted</q>"

  @javascript
  Scenario: Tags h1, h5, h6 can exist in a formatted text but the user does not have these options on the editor.
    Given discussion content:
      | title         | body                                                                                                  | collection    |
      | Ragged Crying | <h1>test h1</h1> <h2>test h2</h2> <h3>test h3</h3> <h4>test h4</h4> <h5>test h5</h5> <h6>test h6</h6> | Netflix group |

    When I am logged in as a moderator
    And I go to the "Ragged Crying" discussion
    Then I should see an "h1" element with the text "test h1" in the "Content" region
    Then I should see an "h2" element with the text "test h2" in the "Content" region
    Then I should see an "h3" element with the text "test h3" in the "Content" region
    Then I should see an "h4" element with the text "test h4" in the "Content" region
    Then I should see an "h5" element with the text "test h5" in the "Content" region
    Then I should see an "h6" element with the text "test h6" in the "Content" region

    # Ensure that the user does not have access to disallowed paragraph formats.
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then the paragraph formats in the "Content" field should not contain the "h1, h5, h6" formats

  @backtrackLimit
  Scenario: Ensure really long HTML tag (e.g. < img > with src:data/image...) does not generate a fatal error.
    Given discussion content:
      | title         | body                        | collection    |
      | Famous actors | Article about famous actors | Netflix group |
    And I set long body of "Famous actors" discussion

    When I go to the "Famous actors" discussion
    # In CK5 img tag is removed we are using media.
    Then I should not see the "img" element with the "alt" attribute set to "Image removed." in the "Content" region
