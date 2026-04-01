(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.geofield311 = {
    attach: function (context, settings) {

      // React on leafletMapInit event.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid) {
        // Geojson Overlays
        // If there is Geojson valid file input.
        if (settings.geojson_app_limit && settings.geojson_app_limit.file.length > 0) {
          let geojsonAppLimitSettings = settings.geojson_app_limit;
          Drupal.geojsonAppLimit.manageLeafletGeojsonAppLimit(mapid, lMap, geojsonAppLimitSettings);
        }
      });

      // React on geofieldMapInit
      $(context).on('geofieldMapInit', function (event, mapid) {
        let geofieldMap_settings = settings.geofield_map ? settings.geofield_map[mapid] : (settings.geofield_google_map[mapid] ?? []);
        let map = geofieldMap_settings.map ?? geofieldMap_settings['map_settings'].map;
        // Geojson Overlays
        // If there is Geojson valid file input.
        if (geofieldMap_settings.geojson_app_limit && geofieldMap_settings.geojson_app_limit.file.length > 0) {
          let geojsonAppLimitSettings = geofieldMap_settings.geojson_app_limit;
          if (geofieldMap_settings.map_library === 'leaflet') {
            Drupal.geojsonAppLimit.manageLeafletGeojsonAppLimit(mapid, map, geojsonAppLimitSettings);
          }
          else {
            Drupal.geojsonAppLimit.manageGoogleMapsGeojsonAppLimit(map, geojsonAppLimitSettings);
          }
        }
      });
    }
  };

  Drupal.geojsonAppLimit = {

    widerMapBounds: (bounds, val) => {
      let widingVal = (val) ? val : 0.3;
      bounds._northEast.lat += widingVal;
      bounds._northEast.lng += widingVal;
      bounds._southWest.lat -= widingVal;
      bounds._southWest.lng -= widingVal;
      return bounds;
    },

    // Manage Leaflet GeoJson App Limits.
    manageLeafletGeojsonAppLimit: function (mapid, map, geojsonAppLimitSettings) {
      let self = this;
      $.getJSON(geojsonAppLimitSettings.file, function (data) {

        let geojsonFeatures = data;
        let myStyle = {
          color: geojsonAppLimitSettings.stroke_color,
          weight: parseInt(geojsonAppLimitSettings.stroke_weight),
          opacity: 0.6,
          fillColor: "blue",
          fillOpacity: 0,
          interactive: false,
        };

        let geojsonOptions = {
          style: myStyle,
          onEachFeature: function (feature, layer) {
            let layerBounds = layer.getBounds();
            map.boundsCenter = layerBounds.getCenter();
            map.boundsZoom = map.getBoundsZoom(layerBounds) + (geojsonAppLimitSettings.bounds_zoom_correction ? parseInt(geojsonAppLimitSettings.bounds_zoom_correction) : 0);
            if (geojsonAppLimitSettings.bounds_zoom_flag) {
              map.setView(map.boundsCenter, map.boundsZoom);
              if (Drupal.Leaflet[mapid]) {
                // Set the Map Reset Control settings.
                Drupal.Leaflet[mapid].start_center = map.boundsCenter;
                Drupal.Leaflet[mapid].start_zoom = map.boundsZoom;
              }
            }
            if (geojsonAppLimitSettings.bounds_limit_flag) {
              map.options.minZoom = map.boundsZoom + (geojsonAppLimitSettings.max_zoom_out ? parseInt(geojsonAppLimitSettings.max_zoom_out) : 0);
              // Set MaxBounds to a wider value of the geojson Feature bounds.
              let bounds_widing_val = 0.5;
              map.setMaxBounds(self.widerMapBounds(layerBounds, bounds_widing_val));
            }
          },
          pmIgnore: true
        };

        // Create the geoJson layer, add it to the map and bring it back.
        let LeafletGeoJson = L.geoJson(geojsonFeatures, geojsonOptions);
        LeafletGeoJson.addTo(map).bringToBack();
      });
    },

    // Manage Google Maps GeoJson App Limits.
    manageGoogleMapsGeojsonAppLimit: function (map, geojsonAppLimitSettings) {
      map.data.loadGeoJson(geojsonAppLimitSettings.file, {}, function(features) {
        map.data.setStyle({
          strokeColor: geojsonAppLimitSettings.stroke_color,
          strokeWeight: geojsonAppLimitSettings.stroke_weight,
          strokeOpacity: 1,
          fillColor: "blue",
          fillOpacity: 0,
          clickable: false,
        });
        let layerBounds = new google.maps.LatLngBounds();
        map.data.forEach(function (feature) {
          let geo = feature.getGeometry();
          geo.forEachLatLng(function (LatLng) {
            layerBounds.extend(LatLng);
          });
        });
        let start_center = map.getCenter();
        let start_zoom = map.getZoom();
        // We need to fitBounds anyway, to be able to get the boundsZoom.
        map.fitBounds(layerBounds);
        let boundsZoom = map.getZoom() + parseInt(geojsonAppLimitSettings.bounds_zoom_correction);
        if (!geojsonAppLimitSettings.bounds_zoom_flag) {
          map.setCenter(start_center);
          map.setZoom(start_zoom);
        }
        if (geojsonAppLimitSettings.bounds_limit_flag ?? false) {
          map.setOptions({minZoom: boundsZoom + parseInt(geojsonAppLimitSettings.max_zoom_out)});
          // Set MaxBounds to a wider value of the geojson Feature bounds.
          let bounds_widing_val = 0.5;
          // @TODO: add logic to limit the Map Bounds.
          //  But after Googling it looks it is only possible adding bounds
          //  hanged Event Handler/Listener.
        }
      });
    },
  }

})(jQuery, Drupal);
