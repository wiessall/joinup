@api @group-clone
Feature: Test documentation migration.

  @update:joinup_paragraphs_post_update_200700
  Scenario Outline:
    Given I am logged in as a moderator
    When I visit "/node/<node_id>"
    Then I should <see> the heading "Documentation"
    And I should see "<file>"

    Examples:
      | node_id | see     | file                                                                             |
      # solutions
      | 725136  | see     | https://semiceu.github.io/RRMV/releases/0.2.0/                                   |
      | 726148  | see     | https://academy.europa.eu/courses/the-interoperable-europe-act-introduction      |
      | 726669  | see     | https://docs.mautic.org                                                          |
      | 729332  | see     | https://semiceu.github.io/uri.semic.eu-generated/IOPA/releases/0.0.1/            |
      | 730730  | see     | https://appsemble.app/en/docs                                                    |
      | 731015  | see     | EuroVoc_logo.pdf                                                                 |
      | 731269  | see     | https://drive.google.com/file/d/1b2wHQ6c0nnBVfxha3juNGL3gazDIStgu/view           |
      | 731269  | see     | https://www.youtube.com/watch?v=I_lOvQy3G0o                                      |
      | 731269  | see     | https://oecd-opsi.org/innovations/diia-engine/                                   |
      | 731269  | see     | https://diia-engine.github.io/diia-engine-documentation/en/platform/1.9.8/index… |
      # releases
      | 731316  | see     | https://wiki.sorm.as/                                                            |
      | 719985  | see     | IDPSIO_v2.1.0_Release_notes.docx                                                 |
      | 719987  | see     | https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference/-/releases… |
      | 719999  | see     | https://grass.osgeo.org/grass-stable/manuals/                                    |
      | 720034  | see     | signaturpruefservice_dokumentation_v2.0.1.pdf                                    |
      | 720037  | see     | http://opendata-ajuntament.barcelona.cat/en/caracteristiques-portal#Tecniques    |
      | 720043  | see     | https://github.com/ec-europa/joinup-dev/releases/tag/v1.44.0                     |
      | 720044  | see     | https://github.com/ec-europa/joinup-dev/releases/tag/v1.45.0                     |
      | 720051  | see     | https://github.com/EU-EDPS/website-evidence-collector                            |
      #documents
      | 41245   | not see | eupl.jpg                                                                         |
      | 41393   | not see | pastedGraphic.tiff                                                               |
      | 41400   | not see | the-softwareborsen-case-study.odt                                                |
      | 41402   | not see | ososs-project.odt                                                                |
      | 41406   | not see | schools-in-south-tyrol.odt                                                       |
      | 41407   | not see | schools-in-south-tyrol.pdf                                                       |
      | 41409   | not see | andalusia-floss.odt                                                              |
      | 41410   | not see | andalusia-floss-1.odt                                                            |
      | 41411   | not see | andalusia-floss-1.pdf                                                            |
      | 41424   | not see | bbc-tries-out-open-source.odt                                                    |
    #distributions
      | 711338  | not see | Download CAMSS_Assessment_scenario2_Unicode_v1.0.0.xlsm                          |
      | 711339  | not see | Download domibusConnectorDistribution                                            |
      | 711340  | not see | Download CAMSS method (v0.3) scenario 3.xlsx                                     |
      | 711341  | not see | Download CAMSS method (v0.3) scenario 1.xlsx                                     |
      | 711342  | not see | Download CAMSS method (v0.4) scenario 2.xlsx                                     |
      | 711343  | not see | Download CAMSS method (v0.4) scenario 1.xlsx                                     |
      | 711344  | not see | Download CAMSS method (v0.9) scenario MSP.xlsm                                   |
      | 711345  | not see | Download CAMSS method (v0.9) scenario 3.xlsm                                     |
      | 711346  | not see | Download CAMSS method (v0.9) scenario 2.xlsm                                     |
      | 711347  | not see | Download CAMSS Tools_v1.0_scenario 3.xlsm                                        |
