/**
 * We are overriding the adding features functionality of the Leaflet module.
 */

/**
 * Extend Map Bounds with new lFeature/feature.
 *
 * This overrides the original extend_map_bounds based on the
 * feature boolean property "exclude_from_map_bounds".
 *
 * @param lFeature
 *   The Leaflet Feature
 * @param feature
 *   The Feature coming from Drupal settings.
 *   (this parameter should be kept to eventually extend this method with
 *   conditional logics on feature properties)
 */
Drupal.Leaflet.prototype.extend_map_bounds = function(lFeature, feature) {
  const feature_properties = feature.hasOwnProperty('properties') ? JSON.parse(feature['properties']) : {
    exclude_from_map_bounds: false,
  };
  if (feature.type === 'point' && (!parseInt(feature_properties.exclude_from_map_bounds) || Object.keys(this.features).length === 0)) {
    this.bounds.push([feature.lat, feature.lon]);
  } else if (!parseInt(feature_properties.exclude_from_map_bounds) || Object.keys(this.features).length === 0) {
    this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
  }
};

/**
 * Add Leaflet Tooltip to the Leaflet Feature (override).
 *
 * Set the Leaflet Tooltip, with its options,
 * but omit in case of geoimage content type.
 *
 * @param lFeature
 *   The Leaflet Feature
 * @param feature
 *   The Feature coming from Drupal settings.
 *   The Feature coming from Drupal settings.
 */
Drupal.Leaflet.prototype.feature_bind_tooltip = function(lFeature, feature) {
  if (!this.permanent_tooltip_features) {
    this.permanent_tooltip_features = [];
  }
  const feature_properties = feature.hasOwnProperty('properties') ? JSON.parse(feature['properties']) : {};
  if (feature_properties['content_type'] !== "geoimage" && !parseInt(feature_properties['tooltip_disabled']) && feature.tooltip && feature.tooltip.value.replace(/(<([^>]+)>)/gi, "").trim().length > 0) {
    let tooltip_options = feature.tooltip.options ? JSON.parse(feature.tooltip.options) : {};
    // Need to more correctly set the tooltip_options.permanent option.
    tooltip_options.permanent = tooltip_options.permanent === "true";
    if (tooltip_options.permanent) {
      this.permanent_tooltip_features.push(lFeature);
    }

    // Need to more correctly set the tooltip_options.sticky option.
    tooltip_options.sticky = tooltip_options.sticky === "true";
    lFeature.bindTooltip(feature.tooltip.value, tooltip_options)
  }
};

/**
 * Add Leaflet Popup to the Leaflet Feature (override).
 *
 * @param lFeature
 * @param lFeature
 *   The Leaflet Feature
 * @param feature
 *   The Feature coming from Drupal settings.
 */
Drupal.Leaflet.prototype.feature_bind_popup = function(lFeature, feature) {
  const feature_properties = feature.hasOwnProperty('properties') ? JSON.parse(feature['properties']) : {};
  if (!parseInt(feature_properties['popup_disabled']) && feature.popup) {
    const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};
    lFeature.bindPopup(feature.popup.value, popup_options);
  }
};

(function($, Drupal) {

  // Override the original leaflet.drupal.js methods not to use the
  // L.PolygonClusterable object.

  // Override Leaflet.prototype.create_linestring
  Drupal.Leaflet.prototype.create_linestring = function(polyline) {
    let latlngs = [];
    for (let i = 0; i < polyline.points.length; i++) {
      let latlng = new L.LatLng(polyline.points[i].lat, polyline.points[i].lon);
      latlngs.push(latlng);
    }
    return new L.Polyline(latlngs);
  };

  // Override Leaflet.prototype.create_polygon
  Drupal.Leaflet.prototype.create_polygon = function(polygon) {
    let latlngs = [];
    for (let i = 0; i < polygon.points.length; i++) {
      let latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
      latlngs.push(latlng);
    }
    return new L.Polygon(latlngs);
  };

  // Override Leaflet.prototype.create_multipolygon
  Drupal.Leaflet.prototype.create_multipolygon = function(multipolygon) {
    let polygons = [];
    for (let x = 0; x < multipolygon.component.length; x++) {
      let latlngs = [];
      let polygon = multipolygon.component[x];
      for (let i = 0; i < polygon.points.length; i++) {
        let latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
        latlngs.push(latlng);
      }
      polygons.push(latlngs);
    }
    return new L.Polygon(polygons);
  };

  // Override Leaflet.prototype.create_multipoly
  Drupal.Leaflet.prototype.create_multipoly = function(multipoly) {
    let polygons = [];
    for (let x = 0; x < multipoly.component.length; x++) {
      let latlngs = [];
      let polygon = multipoly.component[x];
      for (let i = 0; i < polygon.points.length; i++) {
        let latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
        latlngs.push(latlng);
      }
      polygons.push(latlngs);
    }
    if (multipoly['multipolyline']) {
      return new L.Polyline(polygons);
    }
    else {
      return new L.Polygon(polygons);
    }
  };

})(jQuery, Drupal);
