@api @tmgmt
Feature: TMGMT Continuous Job Creation
  In order to ensure that content is always translated
  As an administrator
  I want to have continuous jobs automatically created for new content

  Scenario: Automatically create continuous jobs for new multilingual content
    Given no continuous tmgmt jobs exist for the ec_etranslation plugin
    And the following tmgmt jobs should not exist:
      | source language | target language | plugin          |
      | fr              | en              | ec_etranslation |
      | fr              | de              | ec_etranslation |
      | de              | en              | ec_etranslation |
      | de              | fr              | ec_etranslation |
      | en              | fr              | ec_etranslation |
      | en              | de              | ec_etranslation |
    And news multilingual content:
      | title              | langcode | default_translation | field_state |
      | English parent     | en       | 1                   | published   |
      | French translation | fr       | 0                   | published   |
      | German translation | de       | 0                   | published   |
    Then the following tmgmt jobs should not exist:
      | source language | target language | plugin          |
      | fr              | en              | ec_etranslation |
      | fr              | de              | ec_etranslation |
      | de              | en              | ec_etranslation |
      | de              | fr              | ec_etranslation |
    But the following tmgmt jobs should exist:
      | source language | target language | plugin          |
      | en              | fr              | ec_etranslation |
      | en              | de              | ec_etranslation |

    Given news multilingual content:
      | title                  | langcode | default_translation | published   |
      | 2nd French parent      | fr       | 1                   | published   |
      | 2nd German translation | de       | 0                   | published   |
    Then the following tmgmt jobs should not exist:
      | source language | target language | plugin          |
      | de              | en              | ec_etranslation |
      | de              | fr              | ec_etranslation |
    And the following tmgmt jobs should exist:
      | source language | target language | plugin          |
      | en              | fr              | ec_etranslation |
      | en              | de              | ec_etranslation |
      | fr              | en              | ec_etranslation |
      | fr              | de              | ec_etranslation |
