(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.leafletNodeForcedBounds = {
    attach: function(context) {

      // React on leafletMapInit event.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {

        // Check if we're on a node page that needs forced bounds
        const path = drupalSettings.path?.currentPath;
        const bounds = drupalSettings.leaflet?.node_forced_bounds;
        const node_id = bounds?.node_id;
        
        if (!path || !bounds || !node_id || path !== `node/${node_id}`) {
          return;
        }

        // Process features and create forced bounds
        const features = bounds.geofields || [];
        const points = [];
        
        features.forEach(feature => {
          if (feature.type === 'point') {
            points.push(L.latLng(feature.lat, feature.lon));
          } else {
            const lFeature = Drupal.Leaflet.prototype.create_geometry(feature);
            const bounds = lFeature.getBounds();
            points.push(bounds.getSouthWest(), bounds.getNorthEast());
          }
        });

        // Apply the bounds if we have points
        if (points.length > 0) {
          const forced_bounds = new L.LatLngBounds(points);
          const mapRef = Drupal.Leaflet[mapid];
          
          // Update map center and zoom
          mapRef.start_center = forced_bounds.getCenter();
          mapRef.start_zoom = bounds.zoom_start ?? 17;
          lMap.setView(mapRef.start_center, mapRef.start_zoom);
          
          // Update reset view control
          const reset_options = {
            ...mapRef.reset_view_control.options,
            latlng: mapRef.start_center,
            zoom: mapRef.start_zoom
          };
          
          lMap.removeControl(mapRef.reset_view_control);
          mapRef.reset_view_control = L.control.resetView(reset_options).addTo(lMap);
        }

        // Collect markers and paths
        const node_markers = [];
        const node_paths = [];
        
        for (let step = 0; step < 5; step++) {
          const marker_id = `${node_id}-${step}`;
          const node_marker = data_markers[marker_id];
          
          if (!node_marker) {
            continue;
          }
          
          if (node_marker.setStyle) {
            node_paths.push(node_marker);
          } else {
            node_markers.push(node_marker);
          }
        }

        // Force tooltips to be visible
        const tooltipOptions = {
          permanent: true
        };

        if (node_markers.length > 0) {
          node_markers.forEach(marker => {
            if (marker._tooltip && marker._tooltip._content) {
              marker.bindTooltip(marker._tooltip._content, {
                ...tooltipOptions,
                direction: "top"
              });
            }
          });
        } else if (node_paths.length > 0) {
          node_paths.forEach(path => {
            if (path._tooltip && path._tooltip._content) {
              path.bindTooltip(path._tooltip._content, {
                ...tooltipOptions,
                direction: "center"
              });
            }
          });
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
