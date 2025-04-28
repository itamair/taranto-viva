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
  const feature_properties = feature.properties ? JSON.parse(feature.properties) : {
    exclude_from_map_bounds: false,
  };

  const isExcluded = parseInt(feature_properties.exclude_from_map_bounds);
  const isFirstFeature = Object.keys(this.features).length === 0;

  if (isExcluded && !isFirstFeature) {
    return;
  }

  if (feature.type === 'point') {
    this.bounds.push([feature.lat, feature.lon]);
  } else {
    const bounds = lFeature.getBounds();
    this.bounds.push(bounds.getSouthWest(), bounds.getNorthEast());
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
 */
Drupal.Leaflet.prototype.feature_bind_tooltip = function(lFeature, feature) {
  if (!this.permanent_tooltip_features) {
    this.permanent_tooltip_features = [];
  }

  const feature_properties = feature.properties ? JSON.parse(feature.properties) : {};

  // Add this overriding tooltip logics only onto geoplace content type.
  if (feature.tooltip &&
      feature_properties.content_type !== "geoimage" &&
      !parseInt(feature_properties.tooltip_disabled) &&
      feature.tooltip.value.replace(/(<([^>]+)>)/gi, "").trim().length > 0) {

    const tooltip_options = feature.tooltip.options ? JSON.parse(feature.tooltip.options) : {};

    // Need to more correctly set the tooltip_options.permanent option.
    tooltip_options.permanent = tooltip_options.permanent === true || tooltip_options.permanent === "true";
    if (tooltip_options.permanent) {
      this.permanent_tooltip_features.push(lFeature);
    }

    // Need to more correctly set the tooltip_options.sticky option.
    tooltip_options.sticky = tooltip_options.sticky === "true";

    lFeature.bindTooltip(feature.tooltip.value, tooltip_options);
  }
};

/**
 * Add Leaflet Popup to the Leaflet Feature (override).
 *
 * @param lFeature
 *   The Leaflet Feature
 * @param feature
 *   The Feature coming from Drupal settings.
 */
Drupal.Leaflet.prototype.feature_bind_popup = function(lFeature, feature) {
  const feature_properties = feature.properties ? JSON.parse(feature.properties) : {};

  if (!parseInt(feature_properties.popup_disabled) && feature.popup) {
    const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};
    lFeature.bindPopup(feature.popup.value, popup_options);
  }
};

(function($, Drupal) {

  // Override the original leaflet.drupal.js methods not to use the
  // L.PolygonClusterable object.

  // Override Leaflet.prototype.create_linestring
  Drupal.Leaflet.prototype.create_linestring = function(polyline) {
    const latlngs = polyline.points.map(point => new L.LatLng(point.lat, point.lon));
    return new L.Polyline(latlngs);
  };

  // Override Leaflet.prototype.create_polygon
  Drupal.Leaflet.prototype.create_polygon = function(polygon) {
    const coordinates = polygon.points ?? [];
    return new L.Polygon(coordinates);
  };

  // Override Leaflet.prototype.create_multipolygon
  Drupal.Leaflet.prototype.create_multipolygon = function(multipolygon) {
    const coordinates = multipolygon.points ?? [];
    return new L.Polygon(coordinates);
  };

  // Override Leaflet.prototype.create_multipoly
  Drupal.Leaflet.prototype.create_multipoly = function(multipoly) {
    const polygons = multipoly.component.map(polygon => {
      return polygon.points.map(point => new L.LatLng(point.lat, point.lon));
    });

    return multipoly.multipolyline ? new L.Polyline(polygons) : new L.Polygon(polygons);
  };

})(jQuery, Drupal);
