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

    // Cleanup tooltip className from commas, to support multivalue class fields.
    if (tooltip_options.hasOwnProperty('className') && tooltip_options.className.length > 0) {
      tooltip_options.className = tooltip_options.className.replaceAll(",", "");
    }

    // Set a default tooltip direction property as "top", in case not set.
    if (!tooltip_options.hasOwnProperty('direction') || tooltip_options.direction.length === 0) {
      tooltip_options.direction = "top";
    }

    // Need to more correctly set the tooltip_options.permanent option.
    tooltip_options.permanent = tooltip_options.permanent === true || tooltip_options.permanent === "true";
    if (tooltip_options.permanent) {
      this.permanent_tooltip_features.push(lFeature);
    }

    // Need to more correctly set the tooltip_options.sticky option.
    tooltip_options.sticky = tooltip_options.sticky === true || tooltip_options.sticky === "true";

    lFeature.bindTooltip(feature.tooltip.value, tooltip_options);
  }
};

/**
 * Add Leaflet Popup to the Leaflet Feature (override).
 *
 * We need this override because we are adding to the popup.content the Google Maps Links corresponding:
 * - to the feature.icon location, in case of feature.icon (point);
 * - to the feature.path clicked location, in case of feature.path (geometries);
 *
 * @param lFeature
 *   The Leaflet Feature
 * @param feature
 *   The Feature coming from Drupal settings.
 */
Drupal.Leaflet.prototype.feature_bind_popup = function(lFeature, feature) {
  const feature_properties = feature.properties ? JSON.parse(feature.properties) : {};
  const get_gmaps_links = function (lat, lng, address = null) {
    const google_maps_query = typeof address === 'string' && address.trim() !== '' ? encodeURIComponent(address) : lat + '%2C' + lng;
    return '<div class="field field--name-geofield-googlemaps-link field--type-link field--label-hidden field__items">\n' +
      '<div class="field__item"><a href="https://www.google.com/maps/search/?api=1&amp;query=' + google_maps_query + '" target="_blank">Google Maps</a></div>\n' +
      '<div class="field__item"><a href="https://www.google.com/maps/@?api=1&amp;map_action=pano&amp;viewpoint=' + lat + '%2C' + lng + '" target="_blank">Street View</a></div>\n' +
      '</div>';
  }

  if (!parseInt(feature_properties.popup_disabled) && feature.popup && feature.popup.value) {
    const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};

    let lat = feature.lat ?? '';
    let lng = feature.lon ?? '';

    // Append to the feature.popup.value the Google Maps Links corresponding to
    // the feature centroid location.
    let gmaps_links = get_gmaps_links(lat, lng);
    lFeature.bindPopup(feature.popup.value + gmaps_links, popup_options);

    // In case of feature.path replace the gmaps_links corresponding to the
    // popup LatLng place (click location).
    lFeature.on('popupopen', function (e) {
      const popup = e.popup;
      if (e.target._path) {
        const lat = popup.getLatLng().lat;
        const lng = popup.getLatLng().lng;
        gmaps_links = get_gmaps_links(lat, lng);
        popup.setContent(feature.popup.value + gmaps_links);
      }
      // Else, in case the google_map_place is defined, then generate a Google Maps
      // query based on that address.
      else if (
        typeof feature_properties['google_maps_address'] === 'string' &&
        feature_properties['google_maps_address'].trim() !== ''
      ) {
        gmaps_links = get_gmaps_links(null, null, feature_properties['google_maps_address']);
        popup.setContent(feature.popup.value + gmaps_links);
      }
    });
  }
};

(function($, Drupal) {

  /**
   * Return Geometry Construction Base Options.
   *
   * @param feature
   *   The feature definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_geometry_base_options = function(feature) {
    // Assign the marker title value depending if a Marker simple title or a
    // Leaflet tooltip was set.
    let marker_title = '';
    if (feature.title) {
      marker_title = feature.title.replace(/<[^>]*>/g, '').trim()
    }
    else if (feature.tooltip && feature.tooltip.value) {
      marker_title = feature.tooltip.value.replace(/<[^>]*>/g, '').trim();
    }
    return {
      title: marker_title ?? "",
      className: feature.className ? feature.className.replaceAll(",", "") : '',
      alt: marker_title ?? "",
      group_label: feature.group_label ?? '',
    };
  }

  /**
   * Override of Leaflet Point creator, to apply the icon_size_multiplier
   * property.
   *
   * @param feature
   *   The Marker definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_point = function(feature) {
    let feature_properties;
    try {
      feature_properties = JSON.parse(feature.properties);
    }
    catch (e) {
      feature_properties = {};
    }

    const latLng = new L.LatLng(feature.lat, feature.lon);
    let options = this.create_geometry_base_options(feature);
    let lMarker = new L.Marker(latLng, options);

    if (feature.icon) {
      if (feature.icon.iconType && feature.icon.iconType === 'html' && feature.icon.html) {
        let icon = this.create_divicon(feature.icon);
        lMarker.setIcon(icon);
      }
      else if (feature.icon.iconType && feature.icon.iconType === 'circle_marker') {
        try {
          // Extend the options with circle marker specific properties,
          Object.assign(options , feature.icon.circle_marker_options ? JSON.parse(feature.icon.circle_marker_options) : {})
          options.radius = options.radius ? parseInt(options['radius']) : 10;
        }
        catch (e) {
          options = {};
        }
        lMarker = new L.CircleMarker(latLng, options);
      }
      else if (feature.icon.iconUrl) {
        feature.icon.iconSize = feature.icon.iconSize || {};
        const icon_size_multiplier = feature_properties['icon_size_multiplier'] ?? 1;
        feature.icon.iconSize.x = feature.icon.iconSize.x * icon_size_multiplier || this.naturalWidth;
        feature.icon.iconSize.y = feature.icon.iconSize.y * icon_size_multiplier || this.naturalHeight;
        if (feature.icon.shadowUrl) {
          feature.icon.shadowSize = feature.icon.shadowSize || {};
          feature.icon.shadowSize.x = feature.icon.shadowSize.x || this.naturalWidth;
          feature.icon.shadowSize.y = feature.icon.shadowSize.y || this.naturalHeight;
        }
        let icon = this.create_icon(feature.icon);
        lMarker.setIcon(icon);
      }
    }

    return lMarker;
  };

  // Override the original leaflet.drupal.js methods not to use the
  // L.PolygonClusterable object.

  // Override Leaflet.prototype.create_linestring
  Drupal.Leaflet.prototype.create_linestring = function(polyline) {
    const latlngs = polyline.points.map(point => new L.LatLng(point.lat, point.lon));
    const options = this.create_geometry_base_options(polyline);
    return new L.Polyline(latlngs, options);
  };

  // Override Leaflet.prototype.create_polygon
  Drupal.Leaflet.prototype.create_polygon = function(polygon) {
    const coordinates = polygon.points ?? [];
    const options = this.create_geometry_base_options(polygon);
    return new L.Polygon(coordinates, options);
  };

  // Override Leaflet.prototype.create_multipolygon
  Drupal.Leaflet.prototype.create_multipolygon = function(multipolygon) {
    const coordinates = multipolygon.points ?? [];
    const options = this.create_geometry_base_options(multipolygon);
    return new L.Polygon(coordinates, options);
  };

  // Override Leaflet.prototype.create_multipoly
  Drupal.Leaflet.prototype.create_multipoly = function(multipoly) {
    const polygons = multipoly.component.map(polygon => {
      return polygon.points.map(point => new L.LatLng(point.lat, point.lon));
    });
    const options = this.create_geometry_base_options(multipoly);
    return multipoly.multipolyline ? new L.Polyline(polygons, options) : new L.Polygon(polygons, options);
  };

})(jQuery, Drupal);
