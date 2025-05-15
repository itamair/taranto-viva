/**
 * Attach functionality for Leaflet Widget behaviours.
 */
(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.leaflet_geojson_overlays = {
    geoJsonBounds: {},
    geoJson: {},
    attach: function (context, settings) {
      const self = this;
      if (!settings.leaflet) {
        return;
      }

      // For each Leaflet Map defined in the settings (in the actual document).
      $.each(settings.leaflet, function (mapid, leaflet_settings) {
        // Reset the geoJson and geoJsonBounds.
        self.geoJsonBounds = {};
        self.geoJson = {
          "type": "FeatureCollection",
          "features": [],
        };
        if (!mapid.includes("leaflet-map-widget")) {
          return;
        }

        // Ensure the Leaflet Behavior is attached only once to each Leaflet map
        // id element.
        // @see https://www.drupal.org/project/leaflet/issues/3314762#comment-15044223
        const leaflet_elements = $(once('behaviour-leaflet-geojson-overlays', '#' + mapid,));
        leaflet_elements.each(function () {

          const lMap = leaflet_settings.lMap;
          const map_container = $(this);
          const geojsonFieldOverlay = leaflet_settings.leaflet_widget.geojsonFieldOverlay;
          if (typeof geojsonFieldOverlay === 'object' && geojsonFieldOverlay.hasOwnProperty('contents')) {
            const drupalLeafletWidget = new Drupal.Leaflet_Widget(map_container, lMap, leaflet_settings);
            let geojson_style = {};
            try {
              geojson_style = JSON.parse(geojsonFieldOverlay.path);
            }
            catch (e) {
              geojson_style = {};
              return console.error(e);
            }
            // Make the geojson overlay not interact with leaflet-geoman tools
            // and features.
            geojson_style.pmIgnore = true;

            // Enable Geojson Overlays Snapping, if required.
            if (geojsonFieldOverlay.snapping) {
              geojson_style.snapIgnore = false;
            }

            // Transform Points into styled Leaflet Circle Markers.
            geojson_style.pointToLayer = function (feature, latlng) {
              return L.circleMarker(latlng, geojson_style);
            }
            // Collect all promises in an array
            const promises = [];

            geojsonFieldOverlay.contents.forEach(function (item, index) {
              // Try to fetch valid json Geojson content.
              try {
                promises.push(self.processGeoJsonSource(item, geojson_style, lMap, mapid, drupalLeafletWidget, geojsonFieldOverlay));
              }
              catch (e) {
                console.error('Error initiating GeoJSON processing:', e);
              }
            });

            // Wait for all GeoJSON sources to be processed
            Promise.all(promises).then(() => {
                // Process the GeoJSON overlay if we have features
                if (self.geoJson.features.length > 0) {
                  self.processGeoJsonOverlay(self.geoJson, geojson_style, lMap, mapid, drupalLeafletWidget, geojsonFieldOverlay);
                }
              }).catch(error => {
              // Don't log anything in this case, for now.
              // console.error('Error in GeoJSON processing:', error);
            });
          }
        });
      });
    },

    processGeoJsonSource(item, geojson_style, lMap, mapid, drupalLeafletWidget, geojsonFieldOverlay) {
      const self = this;
      const source = item.uri ?? item.value;

      return new Promise((resolve, reject) => {
        // Only proceed with AJAX if source matches item.uri
        if (source === item.uri) {
          $.getJSON(source)
            .done(function(geoJsonContent) {
              self.geoJson.features.push(geoJsonContent);
              resolve();
            })
        } else {
          // If source is not a URI (likely direct JSON), parse it directly
          const geoJsonContent = JSON.parse(source);
          self.geoJson.features.push(geoJsonContent);
          resolve();
        }
      })
    },

    /**
     * Set GeoJson Overlay and add it to map.
     */
    processGeoJsonOverlay(geoJsonContent, geojson_style, lMap, mapid, drupalLeafletWidget, geojsonFieldOverlay) {
      const LeafletGeoJson = this.setGeoJsonOverlay(geoJsonContent, geojson_style, lMap, mapid);
      if ($.isEmptyObject(drupalLeafletWidget.get_json_value()) && geojsonFieldOverlay.zoom_to_geojson) {
        this.extendGeoJsonBounds(LeafletGeoJson);
        // If geoJsonBounds are properly defined, then fit Leaflet Map
        // bounds and reset the StartZoom and StartCenter.
        if (!$.isEmptyObject(this.geoJsonBounds) && this.geoJsonBounds.isValid()) {
          this.fitMapBoundsAndResetMapInitialView(mapid, lMap);
        }
      }
    },

    /**
     * Set GeoJson Overlay and add it to map.
     */
    setGeoJsonOverlay(geoJsonContent, geojson_style, lMap, mapId) {
      const LeafletGeoJson = L.geoJson(geoJsonContent,  geojson_style);
      LeafletGeoJson.addTo(lMap);
      if (Drupal.Leaflet[mapId].layer_control) {
        Drupal.Leaflet[mapId].layer_control.addOverlay(LeafletGeoJson, 'Map (Geojson) Overlays');
      }
      else {
        Drupal.Leaflet[mapId].layer_control = new L.Control.Layers([], {'Map (Geojson) Overlays': LeafletGeoJson}).addTo(Drupal.Leaflet[mapId].lMap);
      }
      return LeafletGeoJson;
    },

    /**
     * Set GeoJson Overlay and add to map.
     */
    extendGeoJsonBounds(geoJsonContent) {
      // Define or extend Geojson bounds to make the Leaflet Map fit them.
      if (!$.isEmptyObject(this.geoJsonBounds)) {
        this.geoJsonBounds.extend(geoJsonContent.getBounds());
      }
      else {
        this.geoJsonBounds = geoJsonContent.getBounds();
      }
    },

    /**
     * Fit GeoJson Bounds and Reset Leaflet initial Map Center and Zoom.
     */
    fitMapBoundsAndResetMapInitialView(mapid, map) {
      map.fitBounds(this.geoJsonBounds);
      Drupal.Leaflet_Widget.prototype.reset_start_zoom_and_center(mapid, map.getBoundsZoom(this.geoJsonBounds), this.geoJsonBounds.getCenter());
    }

  }

})(jQuery, Drupal, drupalSettings, once);
