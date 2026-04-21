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

        // VectorGrid's PointSymbolizer extends L.CircleMarker and inherits
        // getLatLng(), but never sets _latlng — only the pixel-space _point.
        // Leaflet's _fireDOMEvent classifies any target with getLatLng and
        // _radius ≤ 10 as a "marker" and calls latLngToContainerPoint(getLatLng()),
        // which crashes on undefined.
        //
        // With the default SVG renderer the PointSymbolizer is registered in
        // map._targets (keyed on its SVG path), so the crash path is:
        //   _handleDOMEvent → _fireDOMEvent(e, type) → _findEventTargets → crash
        // Patching _findEventTargets intercepts that path.
        //
        // With the canvas renderer the crash path is instead:
        //   L.Canvas._fireEvent → _fireDOMEvent(e, type, canvasTargets) → crash
        // Patching _fireDOMEvent intercepts that path.
        //
        // Both patches wrap the broken target in an Object.create proxy that
        // hides getLatLng (sets it to undefined), so Leaflet falls back to
        // mouseEventToContainerPoint(e). All other methods delegate to the real
        // target, so event propagation and e.layer.properties remain intact.
        const patchTarget = function (t) {
          if (t.getLatLng && t.getLatLng() === undefined) {
            const proxy = Object.create(t);
            proxy.getLatLng = undefined;
            return proxy;
          }
          return t;
        };

        const _origFindEventTargets = lMap._findEventTargets.bind(lMap);
        lMap._findEventTargets = function (ev, type) {
          return _origFindEventTargets(ev, type).map(patchTarget);
        };

        const _origFireDOMEvent = lMap._fireDOMEvent.bind(lMap);
        lMap._fireDOMEvent = function (ev, type, canvasTargets) {
          if (canvasTargets) {
            canvasTargets = canvasTargets.map(patchTarget);
          }
          return _origFireDOMEvent(ev, type, canvasTargets);
        };

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
          maxNativeZoom: 19,
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
