@api @eu-oss-catalogue

Feature:
  In order to present the details about EU OSS catalogue
  I want to view a dedicated collection and landing page

  Scenario: User sees short description on OSS Solution page.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status    | oss_release_date |
      | Foo   | development            | developers_italia | data-analytics | Foo's short description | Foo's GIT description | published | 2014-11-19       |
    When I visit the "Foo" "oss_solution"
    Then I should see the text "Foo's short description"
    But I should not see the text "Foo's GIT description"

  Scenario: User can see the OSS header.
    Given oss_solution content:
      | title   | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status    | oss_release_date |
      | Olympus | development            | developers_italia | data-analytics | Foo's short description | Foo's GIT description | published | 2014-11-19       |
    When I visit the "Olympus" "oss_solution"

  Scenario: User can see GIT repo indicators.
    Given oss_solution content:
      | title              | oss_source        | oss_git_stars | oss_git_open_issues | oss_git_open_pull_requests | oss_git_forks | status    |
      | Olympus indicators | developers_italia | 1111          | 2222                | 3333                       | 4444          | published |
    When I visit the "Olympus indicators" "oss_solution"
    Then I should see the following lines of text:
      | 1111 |
      | 2222 |
      | 3333 |
      | 4444 |

  Scenario: OSS Catalogue Manager can see unpublished OSS solutions.
    Given oss_solution content:
      | title   | oss_development_status | oss_source        | oss_categories | oss_short_description   | oss_git_description   | status      | oss_release_date |
      | Olympus | development            | developers_italia | data-analytics | Foo's short description | Foo's GIT description | unpublished | 2014-11-19       |

    When I am logged in as a user with the "oss_catalogue_manager" role
    And I visit the "Olympus" "oss_solution"
    Then I should see the heading "Olympus"

    When I visit "/admin/content/eu-oss-catalogue/oss-solutions"
    Then the available options in the "Action" select should be "-- Select action --,Unpublish with all translations,Publish with all translations"
    And the "EU OSS Catalogue administration" table should contain the following columns:
      | Title   | URL | Short description       | Source Catalogue  | Languages | Published |
      | Olympus |     | Foo's short description | Developers Italia | EN        | No        |

    When I visit "/admin/content/eu-oss-catalogue/log"

  Scenario: User sees GIT description on OSS Solution page if short description is missing.
    Given oss_solution content:
      | title | oss_development_status | oss_source        | oss_categories | oss_short_description | oss_git_description   | status    | oss_release_date |
      | Foo   | stable                 | developers_italia | data-analytics |                       | Foo's GIT description | published | 2014-11-19       |
    When I visit the "Foo" "oss_solution"
    Then I should see the text "Foo's GIT description"

  @loggedErrors
  Scenario: All necessary OSS Solution fields are visible on node page.
    Given oss_contact content:
      | title           |
      | FOSS Conference |
    Given oss_contractor content:
      | title     |
      | ACME Ltd. |
    Given oss_used_by terms:
      | name             |
      | Administration 1 |
      | Administration 2 |
      | Administration 3 |
    Given oss_platform terms:
      | name  |
      | macOs |
    Given oss_solution content:
      | title                  | oss_available_languages | oss_development_status | oss_source        | oss_categories                    | oss_long_description                                                                             | oss_short_description | oss_git_description | status    | oss_release_date | oss_documentation   | oss_features         | oss_git_url         | oss_landing_url     | oss_license      | oss_logo                         | oss_maintenance_contacts | oss_maintenance_contractors | oss_maintenance_type | oss_owner       | oss_platforms | oss_publiccode_yml_version | oss_scope                       | oss_screenshots                                                                                    | oss_software_type  | oss_software_version | oss_vitality_index | oss_vitality_index_range               | oss_used_by                                        | oss_validation_status |
      | Super Awesome Software | it,en,de,error,pt       | stable                 | developers_italia | document-management,collaboration | The long description. A patient developer can make this long enough to be at least 5 lines long. | The short description | The GIT description | published | 2014-11-19       | https://example.com | Feature A, Feature B | https://example.com | https://example.com | GPL-2.0-or-later | https://placehold.co/200x200.png | FOSS Conference          | ACME Ltd.                   | community            | FOSS Conference | macOs         | 0.4                        | science-and-technology,security | https://placehold.co/600x400.png,https://placehold.co/400x400.png,https://placehold.co/800x400.jpg | standalone/desktop | 3.17.143             | 0.419              | "[12,45,56,7,90,29,34,20,47,48,50,80]" | Administration 1,Administration 2,Administration 3 | valid                 |
    When I visit the "Super Awesome Software" "oss_solution"
    Then I should be on "/eu-oss-catalogue/solutions/super-awesome-software"
    And the site should not have log records with level lower than "notice" for "php" errors
    And I should see the following headings:
      | Super Awesome Software |
      | Description            |
      | Features               |
      | Intended audience      |
      | Detailed information   |
      | Gallery                |
    And I should see the following lines of text:
      | Source catalogue                                                                                 |
      | The short description                                                                            |
      | Description                                                                                      |
      | The long description. A patient developer can make this long enough to be at least 5 lines long. |
      | Copyrights owner                                                                                 |
      | FOSS Conference                                                                                  |
      | Features                                                                                         |
      | Feature A                                                                                        |
      | Feature B                                                                                        |
      | Supported languages                                                                              |
      | Italian,                                                                                         |
      | English,                                                                                         |
      | German,                                                                                          |
      | error,                                                                                           |
      | Portuguese, International                                                                        |
      | Development status                                                                               |
      | Stable                                                                                           |
      | License                                                                                          |
      | GPL-2.0-or-later                                                                                 |
      | Platforms                                                                                        |
      | macOs                                                                                            |
      | Maintenance type                                                                                 |
      | Maintained by people donating their time to the project                                          |
      | Software type                                                                                    |
      | standalone/desktop                                                                               |
      | Used by                                                                                          |
      | Administration 1,                                                                                |
      | Administration 2,                                                                                |
      | Administration 3                                                                                 |
      | Technical Maintainers                                                                            |
      | ACME Ltd.                                                                                        |
      | Technical Contacts                                                                               |
      | FOSS Conference                                                                                  |
      | Vitality                                                                                         |
      | 41%                                                                                              |
      | publiccode.yml is valid                                                                          |
    And the "div" element with "title" attribute should contain "The software is typically installed and run in a a desktop operating system environment."
    And I should not see any HTML tags inside elements with title attribute
    And the "canvas" element with "data-vitality-index-range" attribute should contain "[12,45,56,7,90,29,34,20,47,48,50,80]"
    And I should see the following links:
      # Categories.
      | document-management    |
      | collaboration          |
      # Providers.
      | Developers Italia      |
      # Links to resources.
      | Source code repository |
      | Project’s website      |
      | Documentation          |
      # Audience
      | science-and-technology |
      | security               |
    And I should not see the following links:
      | Show all 3 administrations       |
      | Show administration              |
      # We should see the actual images instead.
      | https://placehold.co/600x400.png |
      | https://placehold.co/400x400.png |
      | https://placehold.co/800x400.jpg |
    But I should have the following images on the page in this order
      # Downloaded instance of https://placehold.co/600x400.png
      | 99ccd804281fede2d4e3dcc3a9b30a4c.png |
      # Downloaded instance of https://placehold.co/400x400.png.
      | 93ac8cf04f904a4f19a8418fd565a448.png |
      # Downloaded instance of https://placehold.co/800x400.jpg.
      | aeea83704c583614d4900c69de40802c.jpg |
    # Clicking links of Categories, Providers or Audience must navigate to
    # search.
    When I click "document-management"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "document-management"
    When I visit the "Super Awesome Software" "oss_solution"
    And I click "Developers Italia"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "Developers Italia"
    When I visit the "Super Awesome Software" "oss_solution"
    And I click "science-and-technology"
    Then I should be on "/eu-oss-catalogue/solutions"
    And I should see the following facet summary "science-and-technology"

    # Check that this node type doesn't leak in search as facet.
    When I visit "/search"
    Then I should not see the link "Open source software solution"

  @javascript
  Scenario: Modal link should be displayed and must show all field values.
    Given oss_used_by terms:
      | name             |
      | Administration 1 |
      | Administration 2 |
      | Administration 3 |
      | Administration 4 |
      | Administration 5 |
      | Administration 6 |
      | Administration 7 |
    And oss_solution content:
      | title      | status    | oss_release_date | oss_validation_errors | oss_available_languages                | oss_used_by                                                                                                            |
      | With modal | published | 2014-11-19       | There is an error     | it,en,de,pt,eo,fr,he,ja,lt,pl,ro,gd,si | Administration 1,Administration 2,Administration 3,Administration 4,Administration 5,Administration 6,Administration 7 |

    When I visit the "With modal" "oss_solution"

    # Check "Used by" field.
    Then I should see the following lines of text:
      | Used by           |
      | Administration 1, |
      | Administration 2, |
      | Administration 3, |
      | Administration 4, |
      | Administration 5  |
    But I should not see the following lines of text:
      | Administration 6 |
      | Administration 7 |
    And I should not see the link "Show administration"
    But I should see the link "Show all 7 administrations"
    When I click "Show all 7 administrations"
    Then a modal should open
    And I should see the following lines of text:
      | 7 administrations are currently using the With modal solution: |
      | Administration 1                                               |
      | Administration 2                                               |
      | Administration 3                                               |
      | Administration 4                                               |
      | Administration 5                                               |
      | Administration 6                                               |
      | Administration 7                                               |
    When I click on element ".ui-dialog .ui-dialog-titlebar-close"
    Then the modal should be closed
    And I should not see the following lines of text:
      | Administration 6 |
      | Administration 7 |

    # Check "Supported languages" field.
    Then I should see the following lines of text:
      | Supported languages |
      | Italian,            |
      | English,            |
    And I should see the link "Show all 13 languages"
    But I should not see the following lines of text:
      | Romanian,     |
      | Scots Gaelic, |
    When I click "Show all 13 languages"
    Then a modal should open
    And I should see the following lines of text:
      | Italian      |
      | English      |
      | Romanian     |
      | Scots Gaelic |
    When I click on element ".ui-dialog .ui-dialog-titlebar-close"
    Then the modal should be closed
    And I should not see the following lines of text:
      | Romanian,     |
      | Scots Gaelic, |
    And I should not see the text "There is an error"
    But I should see the link "Show publiccode.yml violations"
    When I click "Show publiccode.yml violations"
    Then a modal should open
    And I should see the text "There is an error"

  @javascript
  Scenario: Screenshot carousel with 4 items works as expected.
    Given oss_solution content:
      | title | oss_screenshots                                                                                                                     | oss_development_status | oss_source        | oss_categories | status    | oss_release_date |
      | Four  | https://placehold.co/800x400.jpg,https://placehold.co/500x400.png,https://placehold.co/700x400.png,https://placehold.co/600x400.png | stable                 | developers_italia | data-analytics | published | 2014-11-19       |
    When I visit the "Four" "oss_solution"
    Then I should see the text "Gallery"
    And I should have the following images on the page in this order
      # Downloaded instance of https://placehold.co/800x400.jpg.
      | aeea83704c583614d4900c69de40802c.jpg |
      # Downloaded instance of https://placehold.co/500x400.png.
      | b68f93a5aca1d09eb50e9ea901b90d7c.png |
      # Downloaded instance of https://placehold.co/700x400.png
      | ef8676b28d34d82c4a0688a35ec76335.png |
      # Downloaded instance of https://placehold.co/600x400.png
      | 99ccd804281fede2d4e3dcc3a9b30a4c.png |
    # Assuming 1600px wide browser window.
    And I should see the following images on the page
      # First 3 images must be visible.
      | aeea83704c583614d4900c69de40802c.jpg |
      | b68f93a5aca1d09eb50e9ea901b90d7c.png |
      | ef8676b28d34d82c4a0688a35ec76335.png |
    # Fourth must not be visible yet.
    And I should not see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Next']:not([aria-disabled='true'])"
    And I wait for CSS animations to finish
    And I should see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    Then I click on element ".slick--optionset--image-gallery button[aria-label='Previous']:not([aria-disabled='true'])"
    And I wait for CSS animations to finish
    And I should not see the image "99ccd804281fede2d4e3dcc3a9b30a4c.png"
    And I should see a ".slick--optionset--image-gallery button[aria-label='Previous'][aria-disabled='true']" element

  # Regression test for ISAICP-9113 ticket.
  Scenario Outline: SparQL parameter does not throws exception when converting missing type
    When I go to "<path>"
    Then I should get a 404 HTTP response

    Examples:
      | path                      |
      | /modal/foo/modal/bar/like |
      | /feature/foo/bar/add      |
      | /feature/foo/bar/remove   |

  Scenario: OSS catalogue source page
    When I go to "eu-oss-catalogue/source/developers_italia"
    Then I should see the following lines of text:
      | Type: National                                                                                                                                                                                                                                                                    |
      | Country: Italy                                                                                                                                                                                                                                                                    |
      | Description:                                                                                                                                                                                                                                                                      |
      | Developers Italia is a community of developers who design and code Italian digital public services, and provide the Italian National Catalogue for the acquisition and reuse of software by and for the Public Administration, as envisaged and required by national legislation. |
      | Weblinks:                                                                                                                                                                                                                                                                         |
    And I should see the link "https://developers.italia.it/en/software"

    When I go to "eu-oss-catalogue/source/hosting_platform:code_europa_eu"
    Then I should see the heading "Source Catalogue Info"
    And I should see the heading "Code Europa EU"
    And I should see the following lines of text:
      | Type: European                                                                                                                                                                                      |
      | Description:                                                                                                                                                                                        |
      | Code development platform for Open Source projects shared by the institutions of the European Union, for which EU institutions, bodies, offices and agencies hold the intellectual property rights. |
      | Weblinks:                                                                                                                                                                                           |
    And I should see the link "https://code.europa.eu"

  @javascript
  Scenario: User can click on info icon for source catalogue.
    Given oss_solution content:
      | title | oss_source                      | status    |
      | macOS | hosting_platform:code_europa_eu | published |

    When I visit the "macOS" "oss_solution"
    And I click "Get more details on Code Europa EU"

    Then a modal should open
    And I should see the text "Source Catalogue Info" in the "Modal title"
    And I should see the text "Code Europa EU" in the "Modal content"
    And I should see the text "Code development platform for Open Source projects shared by the institutions of the European Union" in the "Modal content"
