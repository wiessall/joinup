/**
 * @file
 * Semic map renderer.
 */

L.custom = {
  init(obj) {
    // CUSTOM.
    obj.style.minHeight = "400px";

    // INIT MAP OBJECT.
    const map = L.map(obj, { center: [48, 9], zoom: 4 });

    // TILES.
    L.wt.tileLayer().addTo(map);

    // MARKERS.
    const kml = drupalSettings.semic.map.adoptersListUrl;

    const kmlOptions = {
      onEachFeature(feature, layer) {
        layer.bindInfo(
          `<p><b>${feature.properties.name}</b></p>${feature.properties.description}`,
        );
      },
    };

    L.wt.markers([kml], kmlOptions).addTo(map);
    L.wt._queue("next"); // Process next components.
  },
};
