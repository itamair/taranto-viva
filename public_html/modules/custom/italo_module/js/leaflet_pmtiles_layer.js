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

        const map = lMap;

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

        const _origFindEventTargets = map._findEventTargets.bind(map);
        map._findEventTargets = function (ev, type) {
          return _origFindEventTargets(ev, type).map(patchTarget);
        };

        const _origFireDOMEvent = map._fireDOMEvent.bind(map);
        map._fireDOMEvent = function (ev, type, canvasTargets) {
          if (canvasTargets) {
            canvasTargets = canvasTargets.map(patchTarget);
          }
          return _origFireDOMEvent(ev, type, canvasTargets);
        };

        const pmtilesUrl = drupalSettings.leaflet_pmtiles_layer?.url
          || 'https://overturemaps-tiles-us-west-2-beta.s3.amazonaws.com/2026-01-21/places.pmtiles';

        const pmtiles_svg_icons_path = "/sites/default/files/pmtiles_svg_icons/";

        // Each entry maps one icon URL to an expandable list of category keywords.
        // A place matches if its primary category contains any keyword in the list.
        const categoryIconEntries = [
          {
            iconUrl: pmtiles_svg_icons_path + 'accommodation-2-svgrepo-com.svg',
            keywords: ['bed_and_breakfast'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'restaurant-svgrepo-com.svg',
            keywords: ['restaurant', 'diner', 'pub'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'museum-svgrepo-com.svg',
            keywords: ['historical_building'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'parking-svgrepo-com.svg',
            keywords: ['parking'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'hotel-sign-hotel-svgrepo-com.svg',
            keywords: ['hotel'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'bar-svgrepo-com.svg',
            keywords: ['bar', 'cafe', 'coffee'],
          },
          {
            iconUrl: pmtiles_svg_icons_path + 'red-dot-svgrepo-com.svg',
            keywords: ['shopping', 'clothing_store', 'store'],
          },
        ];

        // Returns the icon URL for the first entry whose keywords contain
        // a substring of the given category string, or undefined if none matches.
        const getCategoryIconUrl = function (category) {
          const entry = categoryIconEntries.find(function (e) {
            return e.keywords.some(function (k) { return category.includes(k); });
          });
          return entry ? entry.iconUrl : undefined;
        };

        const layerOptions = {
          // Overture Maps places tiles expose a single layer named "place".
          // VectorGrid looks up styles by exact layer name — wildcards are not supported.
          vectorTileLayerStyles: {
            'place': function (properties) {
              const categoryData = properties.categories

                ? JSON.parse(properties.categories) : {};
              const category = categoryData?.primary || '';
              const iconUrl = getCategoryIconUrl(category);

              if (iconUrl) {
                return {
                  icon: L.icon({
                    iconUrl: iconUrl,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10],
                  }),
                };
              }

              return {
                radius: 3,
                weight: 1,
                color: '#ffffff',
                opacity: 0.6,
                fill: true,
                fillColor: '#e05b2e',
                fillOpacity: 0.6,
              };
            },
          },
          // Filter out places that have a low properties.confidence.
          filter: function (properties) {
            return properties.confidence === undefined || properties.confidence >= 0.8;
          },
          // Interactive: false keeps these tiles read-only for performance.
          // Set to true if you want click/hover popups on individual places.
          interactive: true,
          getFeatureId: function (feature) {
            return feature.properties.id || feature.properties.name;
          },
          maxNativeZoom: 19,
          minZoom: Drupal.Leaflet[mapid].placesZoomLimit,
          // L.VectorGrid extends L.GridLayer, which defaults to tilePane (z-index 200),
          // the same pane as base tiles. overlayPane (z-index 400) always renders above
          // all tile layers regardless of which base layer is active.
          pane: 'overlayPane',
        };

        const pmtilesLayer = L.pmtilesLayer(pmtilesUrl, layerOptions);

        // Add the pmtilesLayer as a named overlay so it appears in the Leaflet layer control.
        if (Drupal.Leaflet && Drupal.Leaflet[mapid] && Drupal.Leaflet[mapid].layer_control && (Object.keys(Drupal.Leaflet[mapid].overlays).length !== 0)) {
          const overlayName = drupalSettings.leaflet_pmtiles_layer?.overlay_label
            || Drupal.Leaflet[mapid].placesOverlayName;
          Drupal.Leaflet[mapid].overlays[overlayName] = pmtilesLayer;
          Drupal.Leaflet[mapid].layer_control.addOverlay(Drupal.Leaflet[mapid].overlays[overlayName], overlayName);

          // VectorGrid canvas tiles use pointer-events:auto (interactive:true), so
          // when they are inserted into the DOM during onAdd the browser can fire a
          // synthetic mouseleave on the layer control container and collapse the panel.
          // Re-expanding both synchronously (catches a collapse already in progress)
          // and via setTimeout (catches a queued mouseleave that fires after the
          // current call-stack) keeps the panel open after the user re-enables the overlay.
          map.on('overlayadd', function (e) {
            if (e.layer !== pmtilesLayer) {
              return;
            }
            const lc = Drupal.Leaflet[mapid] && Drupal.Leaflet[mapid].layer_control;
            if (!lc) {
              return;
            }
            lc.expand();
            setTimeout(function () { lc.expand(); }, 0);
          });
        }

        // Leaflet's CSS pointer-events rule only covers svg path.leaflet-interactive,
        // not <image> elements. Setting the map container cursor via JS events is
        // the only reliable way to show pointer cursor on VectorGrid icon markers.
        pmtilesLayer.on('mouseover', function (e) {
          const props = e.layer?.properties || {};
          const categoryData = props.categories ? JSON.parse(props.categories) : {};
          const category = categoryData?.primary || '';
          if (getCategoryIconUrl(category)) {
            map.getContainer().style.cursor = 'pointer';
          }
        });
        pmtilesLayer.on('mouseout', function () {
          map.getContainer().style.cursor = '';
        });

        // Add a basic popup on feature click to show place name/category.
        pmtilesLayer.on('click', function (e) {
          if (!layerOptions.interactive) {
            return;
          }
          const props = e.layer.properties || {};
          const coords = e.latlng || {};
          const name = props['@name'] ?? '—';
          const addresses_data = props.addresses ? JSON.parse(props.addresses) : {};
          const category_data = props.categories ? JSON.parse(props.categories) : {};
          let locality = addresses_data[0].freeform ??  null;
          locality += ' ' + addresses_data[0].locality ??  null;
          const category = category_data?.primary ? category_data?.primary.replaceAll('_', ' ') : '';
          const address = locality ? name + ' - ' + locality : null;
          const gmaps_links = Drupal.Leaflet.prototype.get_gmaps_links(coords.lat, coords.lng, address);
          L.popup()
            .setLatLng(e.latlng)
            .setContent('<strong>' + name + '</strong>' + (category ? '<br>' + category : '') + '<br>' + gmaps_links)
            .openOn(map);
        });

        // pmtilesLayer.addTo(map);
      });
    },

  };

})(jQuery, Drupal, once, drupalSettings);
