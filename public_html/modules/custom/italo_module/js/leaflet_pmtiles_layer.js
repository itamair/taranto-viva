(function ($, Drupal, once, drupalSettings) {

  'use strict';

  /**
   * Attach an Overture Maps PMTiles places layer to the main Leaflet map.
   *
   * L.pmtilesLayer extends L.VectorGrid.Protobuf (bundled in the vendored
   * Leaflet.PMTilesLayer.js IIFE). Styling keys in vectorTileLayerStyles must
   * match the actual layer names inside the PMTiles archive; inspect them with
   * the PMTiles Inspector (https://pmtiles.io) if you need to add more layers.
   */
  Drupal.behaviors.leafletPmtilesLayer = {

    attach(context) {
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid) {
        const elements = once(mapid + '-pmtiles', context.querySelector('#' + mapid) || document);
        if (!elements.length) {
          return;
        }

        const pmtilesUrl = drupalSettings.leaflet_pmtiles_layer?.url
          || 'https://overturemaps-tiles-us-west-2-beta.s3.amazonaws.com/2025-04-23/places.pmtiles';

        const layerOptions = {
          // Overture Maps places tiles expose a single layer named "place".
          // VectorGrid looks up styles by exact layer name — wildcards are not supported.
          vectorTileLayerStyles: {
            'place': function (properties) {
              return {
                radius: 3,
                weight: 1,
                color: '#ffffff',
                opacity: 0.9,
                fill: true,
                fillColor: '#e05b2e',
                fillOpacity: 0.85,
              };
            },
          },
          // Interactive: false keeps these tiles read-only for performance.
          // Set to true if you want click/hover popups on individual places.
          interactive: true,
          getFeatureId: function (feature) {
            return feature.properties.id || feature.properties.name;
          },
          maxNativeZoom: 18,
          minZoom: 17,
        };

        const pmtilesLayer = L.pmtilesLayer(pmtilesUrl, layerOptions);

        // Register as a named overlay so it appears in the Leaflet layer control.
        const overlayLabel = drupalSettings.leaflet_pmtiles_layer?.overlay_label
          || 'Overture Places';

        if (Drupal.Leaflet && Drupal.Leaflet[mapid]) {
          Drupal.Leaflet[mapid].overlays = Drupal.Leaflet[mapid].overlays || {};
          Drupal.Leaflet[mapid].overlays[overlayLabel] = pmtilesLayer;
        }

        // Add a basic popup on feature click to show place name/category.
        pmtilesLayer.on('click', function (e) {
          if (!layerOptions.interactive) {
            return;
          }
          const props = e.layer.properties || {};
          const name = props.names?.primary || props.name || '—';
          const category = props.categories?.primary || props.category || '';
          L.popup()
            .setLatLng(e.latlng)
            .setContent('<strong>' + name + '</strong>' + (category ? '<br>' + category : ''))
            .openOn(lMap);
        });

        pmtilesLayer.addTo(lMap);
      });
    },

  };

})(jQuery, Drupal, once, drupalSettings);
