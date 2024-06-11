@api @group-clone
Feature:
  In order to show the most data to visitors, EU OSS Catalogue provides a
  special equivalent of the OSS Solution's translatable fields which fetch and
  display the field values from the most relevant translation (where the most
  features are listed).

  Scenario: Translatable fields are displayed based on the richest OSS Solution translation
    Given oss_solution multilingual content:
      | title      | status    | changed    | oss_source        | oss_release_date | langcode | default_translation | oss_features              | oss_long_description | oss_short_description    | oss_screenshots | oss_git_url        | oss_publiccode_yml_content                                                                                                                 |
      | EN Title   | published | 1690000000 | developers_italia | 2142-01-01       | en       | 1                   | EN Feature 1              | EN Long description  | EN Short description     |                 | https://github.com | !multiline!description:\n  en:\n    features:\n      0: EN Feature 1\n  it:\n    features:\n      0: IT Feature 1\n      1: IT Feature 2\n |
      |            |           | 1690000000 |                   |                  | it       | 0                   | IT Feature 1,IT Feature 2 | ~~~                  | IT Short description     | IT file 1.jpg   | ~~~                | ~~~                                                                                                                                        |
    When I go to the content page of the type "oss_solution" with the title "EN Title"
    Then I should see the heading "EN Title"
    And I should not see the following lines of text:
      | En Feature 1         |
      | EN Short description |
      | EN Long description  |
    But I should see the following lines of text:
      | IT Short description |
      | IT Feature 1         |
      | IT Feature 2         |

    # Ensures only the default translation URL is exposed.
    Then I should see a "link[rel='alternate'][hreflang='en']" element
    And I should not see a "link[rel='alternate'][hreflang='it']" element

    When I visit "/eu-oss-catalogue"
    Then I should see the "EN Title" tile
    And I should see the text "IT Short description"

    When I visit "/eu-oss-catalogue/solutions"
    Then I should see the "EN Title" tile
    And I should see the text "IT Short description"

    Given I am logged in as a user with the "administrator" role
    And I accept the "Legal notice" agreement
    When I visit "/admin/content/eu-oss-catalogue/oss-solutions"
    Then I should see the heading "Open Source Software Solutions"
    And I should see the "EU OSS Catalogue administration" table
    And the "EU OSS Catalogue administration" table should contain the following columns:
      | Title    | URL                | Short description    | Source Catalogue  | Languages | Published | Changed            |
      | EN Title | https://github.com | IT Short description | Developers Italia | EN, IT    | Yes       | 22/07/2023 - 04:26 |

    # Ensures content translation edit forms can be accessed.
    Given I am logged in as a user with the "developer" role
    When I go to the content page of the type "oss_solution" with the title "EN Title"
    When I click "Translate" in the "Entity actions" region
    Then I should see the heading "Translations of EN Title"
    When I click "Edit" in the "English" row
    And I should see the heading "Edit Open Source Software Solution EN Title"
    Then the "Short description" field should contain "EN Short description"
    And I move backward one page
    When I click "Edit" in the "Italian" row
    And I should see the heading "Edit Open Source Software Solution EN Title [Italian translation]"
    Then the "Short description" field should contain "IT Short description"

  Scenario: Translatable fields are displayed based on the richest OSS Solution translation
    Given oss_solution multilingual content:
      | title        | status    | changed    | oss_source        | oss_release_date | langcode | default_translation | oss_features              | oss_short_description | oss_publiccode_yml_content                                                                                                                                        |
      | ABC Solution | published | 1700000000 | developers_italia | 2025-10-19       | en       | 1                   |                           |                       | !multiline!description:\n  de:\n    features:\n      0: DE Feature 1\n      1: DE Feature 2\n  el:\n    features:\n      0: EL Feature 1\n      1: EL Feature 2\n |
      |              | ~~~       | ~~~        | ~~~               | ~~~              | de       | 0                   | DE Feature 1,DE Feature 2 | ~~~                   |  ~~~                                                                                                                                                              |
      |              | ~~~       | ~~~        | ~~~               | ~~~              | el       | 0                   | EL Feature 1,EL Feature 2 | EL Short description  |  ~~~                                                                                                                                                              |
    When I go to the content page of the type "oss_solution" with the title "ABC Solution"
    Then I should see the heading "ABC Solution"
    And I should not see the following lines of text:
      | EL Short description |
      | EL Feature 1         |
      | EL Feature 2         |
    But I should see the following lines of text:
      | DE Feature 1         |
      | DE Feature 2         |

    Given I am logged in as a user with the "administrator" role
    And I accept the "Legal notice" agreement
    When I visit "/admin/content/eu-oss-catalogue/oss-solutions"
    Then I should see the heading "Open Source Software Solutions"
    And I should see the "EU OSS Catalogue administration" table
    And the "EU OSS Catalogue administration" table should contain the following columns:
      | Title        | URL | Short description | Source Catalogue  | Languages  | Published | Changed            |
      | ABC Solution |     |                   | Developers Italia | EN, DE, EL | Yes       | 14/11/2023 - 22:13 |

