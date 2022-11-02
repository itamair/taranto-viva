/**
 * We are overriding the adding features functionality of the Leaflet module.
 */

(function($, Drupal) {
  Drupal.Leaflet.prototype.add_features = function (features, initial) {
    const leaflet_markercluster_options = this.map_settings.leaflet_markercluster.options && this.map_settings.leaflet_markercluster.options.length > 0 ? JSON.parse(this.map_settings.leaflet_markercluster.options) : {};
    const leaflet_markercluster_include_path = this.map_settings.leaflet_markercluster.include_path;

    // Define a base Layer Cluster, to hold all (ungrouped) Clustered Layers.
    const lBaseCluster = new L.MarkerClusterGroup(leaflet_markercluster_options);
    for (let i = 0; i < features.length; i++) {
      let feature = features[i];
      let lFeature;
      // In case of a Features Cluster Group.
      if (feature.group) {
        // Define a base Layer Group, to hold all (ungrouped) Features Layers.
        const lBaseGroup = this.create_feature_group();
        // Define a new Layer Group Cluster, to hold specific Group Layers.
        const lGroupCluster = new L.MarkerClusterGroup(leaflet_markercluster_options);
        // Define every single Leaflet Feature of the Group.
        for (let groupKey in feature.features) {
          let groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            // If the Leaflet feature is a Path (polygon, polyline, etc.),
            // get and set its path style.
            if (lFeature.setStyle) {
              const lFeature_path_style = groupFeature.path ? (groupFeature.path instanceof Object ? groupFeature.path : JSON.parse(groupFeature.path)) : {};
              lFeature.setStyle(lFeature_path_style);
            }

            // Set the Popup to the single groupFeature.
            if (groupFeature.popup) {
              const popup_options = groupFeature.popup.options ? JSON.parse(groupFeature.popup.options) : {};
              lFeature.bindPopup(groupFeature.popup.value, popup_options);
            }

            // If the Leaflet feature is extending the Path class (Polygon,
            // Polyline, Circle) don't add it to Markercluster if not requested.
            if (lFeature.setStyle && !leaflet_markercluster_include_path) {
              lBaseGroup.addLayer(lFeature);
            }
            else {
              // Add the single Leaflet Feature to the Layer Group Cluster.
              lGroupCluster.addLayer(lFeature);
            }

            // Allow others to do something with the feature that was just added to the map
            $(document).trigger('leaflet.feature', [lFeature, groupFeature, this]);
          }
        }

        // Add the Group Cluster and/or the Base Layer Group as Overlay to the Map.
        if (lGroupCluster.getLayers().length > 0 || lBaseGroup.getLayers().length > 0) {
          this.add_overlay(feature['group_label'], L.featureGroup([lBaseGroup, lGroupCluster]), feature['disabled']);
        }

      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          // If the Leaflet feature is a Path (polygon, polyline, etc.),
          // get and set its path style.
          if (lFeature.setStyle) {
            feature.path = feature.path ? (feature.path instanceof Object ? feature.path : JSON.parse(feature.path)) : {};
            lFeature.setStyle(feature.path);
          }

          // If the Leaflet feature is extending the Path class (Polygon,
          // Polyline, Circle) don't add it to Markercluster.
          if (lFeature.setStyle && !leaflet_markercluster_include_path) {
            this.lMap.addLayer(lFeature);
          }
          else {
            lBaseCluster.addLayer(lFeature);
          }

          // Set the Popup to the Feature.
          if (feature.popup) {
            const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};
            lFeature.bindPopup(feature.popup.value, popup_options);
          }
          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }
      }
    }

    // Add lBaseCluster to the map
    this.add_overlay(null, lBaseCluster, false);

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

})(jQuery, Drupal);
