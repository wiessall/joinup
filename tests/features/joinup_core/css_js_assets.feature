@api @group-d
Feature:
  When script kiddies are fetching the path of some outdated CSS / JS library
  group, our system returns empty file responses instead of throwing and logging
  errors.

  Scenario Outline:
    Given there are no preexisting log records on the site
    And I go to "/sites/default/files/<asset url>"
    Then the response status code should be 200
    And there should be 0 log records

    Examples:
      # The value of the "include" query string is the gz-compressed, base64
      # encoded list of the libraries which should be injected into the page.
      | asset url                                                                                                                        |
      # CSS - library: system/base.
      | css/css_notahash.css?delta=50&language=en&theme=ventuno&include=eJwrriwuSc3VT0osTgUAG5MEcA                                       |
      # JS - at delta=0, there is an external library.
      | js/js_notahahsh.js?delta=0&language=en&theme=ventuno&include=eJzLT40vT00qyc_PKdZPKSotSMzRg_F1i3MTi0py8hNTUosAOvcPwg&scope=header |
