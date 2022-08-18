/**
 * @file
 * Semic map renderer.
 */

L.custom = {
  init(obj, params) {
    // CUSTOM.
    obj.style.minHeight = "400px";

    // INIT MAP OBJECT.
    const map = L.map(obj, { center: [48, 9], zoom: 4 });

    // TILES.
    const tileLayer = L.wt.tileLayer().addTo(map);

    // MARKERS.
    const kml = drupalSettings.semic.map.adoptersListUrl;

    const kml_options = {
      onEachFeature(feature, layer) {
        layer.bindInfo(
          `<p><b>${feature.properties.name}</b></p>${feature.properties.description}`
        );
      },
    };

    const markers = L.wt.markers([kml], kml_options).addTo(map);

    $wt._queue("next"); // Process next components.
  },
};
