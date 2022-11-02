/**
 * We are overriding the adding features functionality of the Leaflet module.
 */

(function($, Drupal) {
  Drupal.Leaflet.prototype.add_features = function (features, initial) {
    const leaflet_markercluster_options = this.map_settings.leaflet_markercluster.options && this.map_settings.leaflet_markercluster.options.length > 0 ? JSON.parse(this.map_settings.leaflet_markercluster.options) : {};
    const leaflet_markercluster_include_path = this.map_settings.leaflet_markercluster.include_path;

    // Define Map Layers holder.
    let layers = {
      // Define a base Layer Group, to hold all (ungrouped) Features Layers.
      _base: this.create_feature_group()
    };

    // Define Map Clusters holder.
    let clusters = {
      // Define a base Layer Cluster, to hold all (ungrouped) Clustered Layers.
      _base: new L.MarkerClusterGroup(leaflet_markercluster_options)
    };

    for (let i = 0; i < features.length; i++) {
      let feature = features[i];
      let lFeature;
      // In case of a Features Cluster Group.
      if (feature.group) {
        // Define a named Layer Group, to hold all unClustered Features Layers.
        layers[feature['group_label']] = this.create_feature_group();
        // Define a new Layer Group Cluster, to hold specific Group Layers.
        clusters[feature['group_label']] = new L.MarkerClusterGroup(leaflet_markercluster_options);
        // Define every single Leaflet Feature of the Group.
        for (let groupKey in feature.features) {
          let groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            // If the Leaflet feature is a Path (polygon, polyline, etc.),
            // get and set its path style.
            if (lFeature.setStyle) {
              this.feature_path_set_style(lFeature, groupFeature);
            }

            // Set the Popup to the single groupFeature.
            this.feature_bind_popup(lFeature, groupFeature)

            // Allow others to do something with the feature that was just added to the map
            $(document).trigger('leaflet.feature', [lFeature, groupFeature, this]);

            // If the Leaflet feature is extending the Path class (Polygon,
            // Polyline, Circle) don't add it to Markercluster if not requested.
            if (lFeature.setStyle && !leaflet_markercluster_include_path) {
              layers[feature['group_label']].addLayer(lFeature);
            }
            else {
              // Add the single Leaflet Feature to the Layer Group Cluster.
              clusters[feature['group_label']].addLayer(lFeature);
            }
          }
        }

        // Add the Group Label Cluster Layer and/or the Group Label Base Layer as Overlay to the Map.
        if (layers[feature['group_label']].getLayers().length > 0 || clusters[feature['group_label']].getLayers().length > 0) {
          this.add_overlay(feature['group_label'], L.featureGroup([layers[feature['group_label']], clusters[feature['group_label']]]), feature['disabled']);
        }
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          // If the Leaflet feature is a Path (polygon, polyline, etc.),
          // get and set its path style.
          if (lFeature.setStyle) {
            this.feature_path_set_style(lFeature, feature);
          }

          // If the Leaflet feature is extending the Path class (Polygon,
          // Polyline, Circle) don't add it to Markercluster.
          if (lFeature.setStyle && !leaflet_markercluster_include_path) {
            layers._base.addLayer(lFeature);
          }
          else {
            clusters._base.addLayer(lFeature);
          }

          // Set the Popup to the Feature.
          this.feature_bind_popup(lFeature, feature)

          // Allow others to do something with the feature that was just added to the map
          $(document).trigger('leaflet.feature', [lFeature, feature, this]);
        }
      }
    }

    // Add lBaseCluster to the map
    this.add_overlay(null, L.featureGroup([layers._base, clusters._base]), false);

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

  Drupal.Leaflet.prototype.feature_add_to_cluster = function(lFeature, feature) {
    // Set the Leaflet Tooltip, with its options (if the stripped value is not null).
    if (feature.tooltip && $(feature.tooltip.value).text().trim()) {
      const tooltip_options = feature.tooltip.options ? JSON.parse(feature.tooltip.options) : {};
      tooltip_options.offset = tooltip_options.offset ?? [0, feature.icon.iconSize ? -feature.icon.iconSize.y/1.5: 0];
      lFeature.bindTooltip(feature.tooltip.value, tooltip_options).openTooltip()
    }
  };

})(jQuery, Drupal);
