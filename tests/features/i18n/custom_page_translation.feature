@api @javascript @group-g
Feature: Translating a custom page.
  In order to make custom pages available in multiple languages
  As a translator
  I need to be able to translate custom page nodes

  Scenario: Translating a custom page.
    Given custom_page content:
      | title                        | abstract                     |
      | Translation Test Custom Page | A short abstract for testing |

    When I am logged in as a developer
    Then I go to the "Translation Test Custom Page" custom_page

    #Translate.
    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Page personnalisée de test de traduction"
    And I fill in "Abstract" with "Un court résumé pour les tests"
    And I press "Update"

    # Verify the translation is visible on the French page.
    Then I go to the "Translation Test Custom Page" custom_page
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then I should see the heading "Page personnalisée de test de traduction"

  Scenario: Translating a custom page with a text paragraph.
    Given custom_page content:
      | title               | abstract                |
      | Text Paragraph Page | Text paragraph abstract |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Text Paragraph Page" title:
      | field_body:value  | This is a test text paragraph. |
      | field_body:format | text_html                      |

    When I am logged in as a developer
    Then I go to the "Text Paragraph Page" custom_page

    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Page paragraphe texte"
    And I fill in "Abstract" with "Résumé du paragraphe texte"
    And I enter "Ceci est un paragraphe de texte de test." in the "Body" wysiwyg editor
    And I press "Update"

    # Verify the paragraph field translation is visible on the French page.
    Then I go to the "Text Paragraph Page" custom_page
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then I should see the heading "Page paragraphe texte"
    And I should see "Ceci est un paragraphe de texte de test."

  Scenario: Translating a custom page with a heading paragraph.
    Given custom_page content:
      | title                  | abstract                   |
      | Heading Paragraph Page | Heading paragraph abstract |
    And I append "heading" to "field_paragraphs_body" field in "node" entity with following "Heading Paragraph Page" title:
      | paragraph_heading | Test heading paragraph                                      |
      | behavior_settings | a:1:{s:16:"heading_behavior";a:1:{s:5:"level";s:2:"h2";}} |

    When I am logged in as a developer
    Then I go to the "Heading Paragraph Page" custom_page

    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Page paragraphe titre"
    And I fill in "Abstract" with "Résumé du paragraphe titre"
    And I fill in "Heading text" with "Paragraphe de titre de test"
    And I press "Update"

    # Verify the paragraph field translation is visible on the French page.
    Then I go to the "Heading Paragraph Page" custom_page
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then I should see the heading "Page paragraphe titre"
    And I should see "Paragraphe de titre de test"

  Scenario: Translating a custom page with a quote paragraph.
    Given custom_page content:
      | title                | abstract                 |
      | Quote Paragraph Page | Quote paragraph abstract |
    And I append "quote" to "field_paragraphs_body" field in "node" entity with following "Quote Paragraph Page" title:
      | paragraph_quote | That's one small step for a man, a giant leap for mankind. |

    When I am logged in as a developer
    Then I go to the "Quote Paragraph Page" custom_page

    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Page paragraphe citation"
    And I fill in "Abstract" with "Résumé du paragraphe citation"
    And I fill in "Quote" with "C'est un petit pas pour l'homme, un bond de géant pour l'humanité."
    And I press "Update"

    # Verify the paragraph field translation is visible on the French page.
    Then I go to the "Quote Paragraph Page" custom_page
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then I should see the heading "Page paragraphe citation"
    And I should see "C'est un petit pas pour l'homme, un bond de géant pour l'humanité."

  Scenario: Translating a custom page with a carousel paragraph.
    Given custom_page content:
      | title                   | abstract                    |
      | Carousel Paragraph Page | Carousel paragraph abstract |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Carousel Paragraph Page" title:
      | paragraph_heading | Carousel heading              |
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I am logged in as a developer
    Then I go to the "Carousel Paragraph Page" custom_page

    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row

    And I fill in "Title" with "Page paragraphe carrousel"
    And I fill in "Abstract" with "Résumé du paragraphe carrousel"
    And I fill in "Heading" with "Titre du carrousel"
    And I press "Update"

    # Verify the paragraph field translation is visible on the French page.
    Then I go to the "Carousel Paragraph Page" custom_page
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then I should see the heading "Page paragraphe carrousel"
    And I should see "Titre du carrousel"
