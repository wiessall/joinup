@api @group-e
Feature: Add distribution through the UI
  In order to manage distributions
  As a facilitator
  I need to be able to add "Distribution" RDF entities through the UI.

  Background:
    Given the following "collection" content:
      | title                   | logo     |
      | Asset Distribution Test | logo.png |
    And the following "solution" content:
      | title                  | collection              | description                      |
      | Solution random x name | Asset Distribution Test | Some reusable random description |
    And the following "release" content:
      | title                | release notes        | release number | creation date    | is version of          | status    |
      | Authoritarian Alpaca | First public release | 1.0.0          | 2019-11-19 20:45 | Solution random x name | Completed |
    And the following "licence" content:
      | title              | description                                              | deprecated |
      | WTFPL              | The WTFPL is a rather permissive licence                 | no         |
      | Deprecated licence | The deprecated licence should not be available to select | yes        |

  Scenario: Add a distribution to a solution as a facilitator.
    When I am logged in as a "facilitator" of the "Solution random x name" solution
    And I go to the homepage of the "Solution random x name" solution
    Then I should see the link "Add distribution"

    When I click "Add distribution"
    Then I should see the heading "Solution random x name" in the "Header" region
    And I should see the heading "Add Distribution"
    And the following fields should be present "Title, Description, Access URL, Licence, Format, Representation technique"
    But the following fields should not be present "Langcode, Translation"
    And the "Licence" field should contain the "WTFPL" options
    But the "Licence" field should not contain the "Deprecated licence" options

    When I press "Publish"
    Then I should see the following inline form error messages:
      | error messages             |
      | Title field is required.   |
      | Licence field is required. |

    And I should see the link "contacting us"
    When I fill in "Title" with "Linux x86-64 SDK"
    And I enter "<p>The full software development kit for systems based on the x86-64 architecture.</p>" in the "Description" wysiwyg editor
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I select "ZIP" from "Format"
    And I select "Datalog" from "Representation technique"
    And I select "WTFPL" from "Licence"
    And I press "Publish"
    And the "Linux x86-64 SDK" distribution should have the link of the "test.zip" in the access URL field

    # Check if the distribution is accessible.
    When I go to the homepage of the "Solution random x name" solution
    Then I should see the text "Distribution"
    And I should see the link "Linux x86-64 SDK"
    When I click "Linux x86-64 SDK"
    Then I should see the heading "Linux x86-64 SDK"
    And I should see the link "WTFPL"
    And I should see the text "The full software development kit for systems based on the x86-64 architecture."

    # The licence label should be shown also in the solution UI.
    When I go to the homepage of the "Solution random x name" solution
    Then I should see the text "WTFPL"

  # Test that unauthorized users cannot add a distribution, both for a release
  # and directly from the solution page.
  Scenario: "Add distribution" button should not be shown to unprivileged users.
    Given I am logged in as a "facilitator" of the "Solution random x name" solution
    And I go to the homepage of the "Authoritarian Alpaca" release
    When I open the plus button menu
    Then I should see the link "Add distribution"

    Given I am logged in as a "member" of the "Asset Distribution Test" collection
    And I go to the homepage of the "Authoritarian Alpaca" release
    When I open the plus button menu
    Then I should not see the link "Add distribution"
    When I go to the homepage of the "Solution random x name" solution
    And I open the plus button menu
    Then I should not see the link "Add distribution"

    Given I am logged in as an "authenticated user"
    And I go to the homepage of the "Authoritarian Alpaca" release
    When I open the plus button menu
    Then I should not see the link "Add distribution"
    When I go to the homepage of the "Solution random x name" solution
    And I open the plus button menu
    Then I should not see the link "Add distribution"

    Given I am an anonymous user
    And I go to the homepage of the "Authoritarian Alpaca" release
    When I open the plus button menu
    Then I should not see the link "Add distribution"
    When I go to the homepage of the "Solution random x name" solution
    And I open the plus button menu
    Then I should not see the link "Add distribution"

  Scenario: Add a distribution to a release as a facilitator.
    When I am logged in as a "facilitator" of the "Solution random x name" solution
    When I go to the homepage of the "Authoritarian Alpaca" release
    And I click "Add distribution" in the plus button menu
    Then I should see the heading "Add Distribution"
    And the following fields should be present "Title, Description, Access URL, Licence, Format, Representation technique"
    And I should see the link "contacting us"
    When I fill in "Title" with "Source tarball"
    And I enter "<p>The full source code.</p>" in the "Description" wysiwyg editor
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I select "WTFPL" from "Licence"
    And I select "Datalog" from "Representation technique"
    And I press "Publish"
    And I should see the text "Download test.zip"
    And I go to the edit form of the "Source tarball" distribution
    And I press "Edit"
    And I fill in "resources[0][subform][resource_file][0][description]" with "My uploaded file"
    And I press "Update"
    And I should not see the text "Download test.zip"
    And I should see the text "Download My uploaded file"

    # Debug step since the default view of the distribution, does not have the access URL shown.
    And the "Source tarball" distribution should have the link of the "test.zip" in the access URL field

    # Check if the distribution is accessible as an anonymous user
    When I am an anonymous user
    When I go to the homepage of the "Authoritarian Alpaca" release
    Then I should see the text "Distribution"
    And I should see the link "Source tarball"
    When I click "Source tarball"
    Then I should see the heading "Source tarball"
    And I should see the link "WTFPL"
    And I should see the heading "Categorisation"
    And I should see the following lines of text:
      | The full source code.    |
      | ZIP                      |
      | Representation technique |
      | Datalog                  |

    And I go to the homepage of the "Solution random x name" solution

  Scenario: The distribution access URL field should accept multiple file extensions.
    Given I am logged in as a "facilitator" of the "Solution random x name" solution
    When I go to the homepage of the "Authoritarian Alpaca" release
    And I click "Add distribution" in the plus button menu
    Then I should see the heading "Add Distribution"

    Then I should see the text "One file only. 64 MB limit. Allowed types: azw css csv dbf doc docx dtd.sgml dtd.xml e00 eps epub fmx2 fmx3 fmx4 gif gml gzip indd jpeg jpg json jsonld kml kmz mdb mobi mop mxd odf ods odt op.datpro owl pdf pdfa1a pdfa1b pdfx png pps ppsx ppt pptx ps psd rdf rdf.turtle rdf.xml rss rtf schema.xml sgml skos.xml sparqlq sparqlqres tar tar.gz tar.xz tiff tsv ttl txt xls xlsx xml xslfo xslt zip."

  Scenario: Adding a distribution with a duplicate title
    Given the following "solution" content:
      | title               | description             |
      | Solubility of gases | Affected by temperature |
    And the following "release" content:
      | title                | release notes        | is version of       |
      | 1.0.0 Adolf Sieverts | First public release | Solubility of gases |

    # Distributions should have a unique title within a single release.
    And I am logged in as a facilitator of the "Solution random x name" solution
    When I go to the homepage of the "Authoritarian Alpaca" release
    And I click "Add distribution" in the plus button menu
    When I fill in "Title" with "MacOSX binary"
    And I select "WTFPL" from "Licence"
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I press "Publish"
    When I click "Add distribution" in the plus button menu
    When I fill in "Title" with "MacOSX binary"
    And I select "WTFPL" from "Licence"
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I press "Publish"
    Then I should see the inline form error message "A distribution with title MacOSX binary already exists in this release. Please choose a different title."

    Given I am logged in as a facilitator of the "Solubility of gases" solution
    When I go to the homepage of the "1.0.0 Adolf Sieverts" release
    And I click "Add distribution" in the plus button menu
    When I fill in "Title" with "MacOSX binary"
    And I select "WTFPL" from "Licence"
    And I press "Publish"

  Scenario: Distributions with the same name should not be allowed within the same solution.
    Given the following "distribution" content:
      | title            | description        | resources  | solution               |
      | Windows - source | Sample description | test.zip   | Solution random x name |
    And I am logged in as a facilitator of the "Solution random x name" solution
    When I go to the homepage of the "Solution random x name" solution
    And I click "Add distribution" in the plus button menu
    When I fill in "Title" with "Windows - source"
    And I select "WTFPL" from "Licence"
    And I press "Publish"
    Then I should see the inline form error message "A distribution with title Windows - source already exists in this solution. Please choose a different title."

  Scenario: Licences are not shown in the solution header.
    Given the following "licence" content:
      | title                  | description                                                                    |
      | Boost Software License | It is a permissive license in the style of the BSD license and the MIT license |
    And the following "distribution" content:
      | title        | licence                | solution               | creation date    | modification date |
      | Hot Snake    | WTFPL                  | Solution random x name | 2019-11-19 20:45 | 2021-11-19 20:45  |
      | Quality Yard | Boost Software License | Solution random x name | 2019-12-19 20:45 | 2021-12-19 20:45  |

    When I go to the homepage of the "Solution random x name" solution
    # Distributions are still shown in the solution page and so the License text will be visible in the page.
    Then I should not see the text "WTFPL" in the "Header" region
    And I should not see the text "Boost Software License" in the "Header" region
    # Assert that the "Distribution" tile is present and contains licence link, label and bundle type.
    But I should see the "Quality Yard" tile
    And I should see the text "Last update: 19/12/2021" in the "Quality Yard" tile
    And I should see the "Hot Snake" tile
    And I should see the text "Last update: 19/11/2021" in the "Hot Snake" tile
    And I should see the text "WTFPL" in the "Hot Snake" tile
    And I should see the text "Distribution" in the "Hot Snake" tile
    # Assert that the "Release" tile is present
    And I should see the "Authoritarian Alpaca" tile

  # Regression test for ticket ISAICP-9239
  Scenario Outline: Check distribution cannot be serialized to JsonLD object.
    Given the following "distribution" content:
      | title   | description        | resources    | solution               |
      | <title> | Sample description | <access url> | Solution random x name |
    When I go to the "<title>" distribution
    And the page should contain the html text '<script type="application/ld+json">'

    Examples:
      | title           | access url               |
      | D example https | https://www.example.com/ |
      | D example http  | http://www.example.com/  |
      | D example ftp   | ftp://ftp.example.com    |
