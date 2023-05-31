@api @group-clone
Feature: Covers EPIC-7409.

  Scenario: CK5 convert old proprietary video embed markup into media (109002).

    # RDF entity ID: http://data.europa.eu/w21/0aa21349-959b-4114-b16f-bb88facd324f
    And the "Description" field of "Electronic Communications Service in Spanish Justice Administration" solution should not match '@\{"preview_thumbnail":"/sites/default/files/styles/video_embed_wysiwyg_preview/public/video_thumbnails/VYPQJHqPUnU.jpg?itok=CDxGF4UO","video_url":"https://youtu.be/VYPQJHqPUnU","settings":\{"responsive":true,"width":"854","height":"480","autoplay":false\},"settings_summary":\["Embedded Video (Responsive)."\]\}@'
    But the "Description" field of "Electronic Communications Service in Spanish Justice Administration" solution should match '@<drupal-media data-entity-type="media" data-entity-uuid="[^"]*"@'

    # RDF entity ID: http://data.europa.eu/w21/0aa21349-959b-4114-b16f-bb88facd324f
    And the "Description" field of "Interoperability Test Bed Repository" collection should not match '@<img alt="Test Bed and validators" data-align="center" data-entity-type="file" data-entity-uuid="4b35b673-9e43-4b9b-907b-7615962d4c6a" src="/sites/default/files/inline-images/itb_and_validator.png"@'
    But the "Description" field of "Interoperability Test Bed Repository" collection should match '@<drupal-media alt="Test Bed and validators" data-entity-type="media" data-entity-uuid="[^"]*"@'

    # Node ID: 706407
    And the "Content" field of "Obtain certified interoperability skills with our Academy!" news should not match '@\{"preview_thumbnail":"/sites/default/files/styles/video_embed_wysiwyg_preview/public/video_thumbnails/4N-_UkN5RY0.jpg?itok=6tAK3tjh","video_url":"https://www.youtube.com/watch?v=4N-_UkN5RY0","settings":\{"responsive":true,"width":"854","height":"480","autoplay":false\},"settings_summary":\["Embedded Video (Responsive)."\]\}@'
    But the "Content" field of "Obtain certified interoperability skills with our Academy!" news should match '@<drupal-media data-entity-type="media" data-entity-uuid="[^"]*"@'
    And a video media with the "https://www.youtube.com/watch?v=4N-_UkN5RY0" URL exists

    # Node ID: 705017
    And the "Description" field of "Presentation: Location Interoperability State of Play – Results of a Europewide Maturity Assessment" document should not match '@<a data-image-style="wysiwyg_one_third_width"@'
    But the "Description" field of "Presentation: Location Interoperability State of Play – Results of a Europewide Maturity Assessment" document should match '@<a href="https://joinup.ec.europa.eu/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8e942bc2-657a-4289-b057-f2a285ee7375"><drupal-media alt="EULF Blueprint" data-entity-type="media" data-entity-uuid="[^"]*"@'

    # Node ID: 706403
    And the "Content" field of "GovTech Connect | Boot camps applications deadline extended!" news should match '@<drupal-media alt="banner" data-entity-type="media" data-entity-uuid="[^"]*"@'

    # Node ID: 705219 (node with nested paragraphs: accordion > accordion item > simple paragraph)
    And the "Body" field of "2nd Body, 1st Accordion item, 1st Item content" paragraph of "Starter pack" "custom page" should not match '@\{"preview_thumbnail":"/sites/default/files/styles/video_embed_wysiwyg_preview/public/video_thumbnails/SwQDVxtwmUs.jpg?itok=RTW5TT_s","video_url":"https://www.youtube.com/watch?v=SwQDVxtwmUs","settings":\{"responsive":true,"width":"854","height":"480","autoplay":false\},"settings_summary":\["Embedded Video (Responsive)."\]\}@'
    But the "Body" field of "2nd Body, 1st Accordion item, 1st Item content" paragraph of "Starter pack" "custom page" should match '@<drupal-media  data-entity-type="media" data-entity-uuid="[^"]*"@'
    And a video media with the "https://www.youtube.com/watch?v=SwQDVxtwmUs" URL exists

    # Node ID: 104310 (node with direct paragraph)
    And the "Body" field of "1st Body" paragraph of "Best Practices Library" "custom page" should not match '@<img alt="" data-entity-type="file" data-entity-uuid="0e5471de-c613-4682-9e4e-8f97afd85927" src="/sites/default/files/inline-images/Picture5.png"@'
    But the "Body" field of "1st Body" paragraph of "Best Practices Library" "custom page" should match '@<drupal-media alt="" data-entity-type="media" data-entity-uuid="[^"]*"@'
