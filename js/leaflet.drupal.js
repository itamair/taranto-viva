(function($, Drupal, drupalSettings) {

  Drupal.behaviors.leaflet = {
    attach: function(context, settings) {

      $.each(settings.leaflet, function(m, data) {
        $('#' + data.mapid, context).each(function() {
          let $container = $(this);
          let mapid = data.mapid;

          // If the attached context contains any leaflet maps, make sure we have a Drupal.leaflet_widget object.
          if ($container.data('leaflet') === undefined) {
            $container.data('leaflet', new Drupal.Leaflet(L.DomUtil.get(mapid), mapid, data.map));
            if (data.features.length > 0) {
              // Add Leaflet Map Features.
              $container.data('leaflet').add_features(data.features, true);
            }

            // Add the leaflet map to our settings object to make it accessible.
            // @NOTE: This is used by the Leaflet Widget module.
            data.lMap = $container.data('leaflet').lMap;

            // Set map position features.
            $container.data('leaflet').fitBounds();
          }

          else {
            // If we already had a map instance, add new features.
            // @TODO Does this work? Needs testing.
            if (data.features !== undefined) {
              $container.data('leaflet').add_features(data.features);
            }
          }

          // Define the global Drupal.Leaflet[mapid] object to be accessible
          // from outside.
          Drupal.Leaflet[mapid] = $container.data('leaflet');

          // Set the start center and the start zoom, and initialize the reset_map control.
          if (!$container.data('leaflet').start_center && !$container.data('leaflet').start_zoom) {
            $container.data('leaflet').start_center = $container.data('leaflet').lMap.getCenter();
            $container.data('leaflet').start_zoom = $container.data('leaflet').lMap.getZoom();
          }

          // Add the Map Reset Control if requested.
          if ($container.data('leaflet').map_settings.reset_map && $container.data('leaflet').map_settings.reset_map.control && !Drupal.Leaflet[mapid].reset_control) {
            // Create the DIV to hold the control and call the mapResetControl()
            // constructor passing in this DIV.
            let mapResetControlDiv = document.createElement('div');
            Drupal.Leaflet[mapid].reset_control = Drupal.Leaflet.prototype.map_reset_control(mapResetControlDiv, mapid).addTo($container.data('leaflet').lMap);
          }

          // Add the Map Geocoder Control if requested.
          if (Drupal.Leaflet.prototype.map_geocoder_control) {
            let mapGeocoderControlDiv = document.createElement('div');
            Drupal.Leaflet[mapid].geocoder_control = Drupal.Leaflet.prototype.map_geocoder_control(mapGeocoderControlDiv, mapid).addTo(Drupal.Leaflet[mapid].lMap);
            let geocoder_settings = drupalSettings.leaflet[mapid].map.settings.geocoder.settings;
            Drupal.Leaflet.prototype.map_geocoder_control.autocomplete(mapid, geocoder_settings);
          }

          // Add Fullscreen Control, if requested.
          if (L.control.fullscreen && $container.data('leaflet').map_settings.fullscreen && $container.data('leaflet').map_settings.fullscreen.control) {
            L.control.fullscreen(
              JSON.parse($container.data('leaflet').map_settings.fullscreen.options)
            ).addTo($container.data('leaflet').lMap);
          }

          // After having initialized the Leaflet Map and added features,
          // allow other modules to get access to it via trigger.
          // NOTE: don't change this trigger arguments print, for back porting
          // compatibility.
          $(document).trigger('leafletMapInit', [data.map, data.lMap, mapid]);
          // (Keep also the pre-existing event for back port compatibility)
          $(document).trigger('leaflet.map', [data.map, data.lMap, mapid]);
        });
      });
    }
  };

  // Once the Leaflet Map is loaded with its features.
  $(document).on('leaflet.map', function(e, settings, lMap, mapid) {

    // Executes once per mapid.
    once('leaflet_map_event_' + mapid, 'html').forEach(function() {

      // Attach leaflet ajax popup listeners.
      Drupal.Leaflet[mapid].lMap.on('popupopen', function(e) {
        let element = e.popup._contentNode;
        let content = $('[data-leaflet-ajax-popup]', element);
        if (content.length) {
          let url = content.data('leaflet-ajax-popup');
          Drupal.ajax({url: url}).execute().done(function () {

            // Copy the html we received via AJAX to the popup, so we won't
            // have to make another AJAX call (#see 3258780).
            e.popup.setContent(element.innerHTML);

            //Call update() so Leaflet refreshes the map, panning it if
            // necessary to bring the full popup into view (#see 3258780).
            e.popup.update();

            // Attach drupal behaviors on new content.
            Drupal.attachBehaviors(element, drupalSettings);
          });
        }
      });
    });
  });

  Drupal.Leaflet = function(container, mapid, map_definition) {
    this.container = container;
    this.mapid = mapid;
    this.map_definition = map_definition;
    this.map_settings = this.map_definition.settings;
    this.bounds = [];
    this.base_layers = {};
    this.overlays = {};
    this.lMap = null;
    this.start_center = null;
    this.start_zoom = null;
    this.layer_control = null;
    this.markers = {};
    this.features = {};
    this.initialise(mapid);
  };

  Drupal.Leaflet.prototype.initialise = function(mapid) {
    // Instantiate a new Leaflet map.
    this.lMap = new L.Map(mapid, this.map_settings);

    // Add map layers (base and overlay layers).
    let i = 0;
    for (let key in this.map_definition.layers) {
      let layer = this.map_definition.layers[key];
      // Distinguish between "base" and "overlay" layers.
      // Default to "base" in case "layer_type" has not been defined in hook_leaflet_map_info().
      layer.layer_type = (typeof layer.layer_type === 'undefined') ? 'base' : layer.layer_type;

      switch (layer.layer_type) {
        case 'overlay':
          let overlay_layer = this.create_layer(layer, key);
          let layer_hidden = (typeof layer.layer_hidden === "undefined") ? false : layer.layer_hidden;
          this.add_overlay(key, overlay_layer, layer_hidden);
          break;

        default:
          this.add_base_layer(key, layer, i);
          // Only the first base layer needs to be added to the map - all the
          // others are accessed via the layer switcher.
          if (i === 0) {
            i++;
          }
          break;
      }
      i++;
    }

    // Set initial view, fallback to displaying the whole world.
    if (this.map_settings.center && this.map_settings.zoom) {
      this.lMap.setView(new L.LatLng(this.map_settings.center.lat, this.map_settings.center.lon), this.map_settings.zoom);
    }
    else {
      this.lMap.fitWorld();
    }

  };

  Drupal.Leaflet.prototype.initialise_layer_control = function() {
    let count_layers = function(obj) {
      // Browser compatibility: Chrome, IE 9+, FF 4+, or Safari 5+.
      // @see http://kangax.github.com/es5-compat-table/
      return Object.keys(obj).length;
    };

    // Only add a layer switcher if it is enabled in settings, and we have
    // at least two base layers or at least one overlay.
    if (this.layer_control == null && ((this.map_settings.layerControl && count_layers(this.base_layers) > 1 || count_layers(this.overlays) > 0))) {
      const base_layers = count_layers(this.base_layers) > 1 ? this.base_layers : [];
      // Instantiate layer control, using settings.layerControl as settings.
      this.layer_control = new L.Control.Layers(base_layers, [], this.map_settings.layerControlOptions);
      this.lMap.addControl(this.layer_control);
    }
  };

  Drupal.Leaflet.prototype.add_base_layer = function(key, definition, i) {
    let base_layer = this.create_layer(definition, key);
    this.base_layers[key] = base_layer;

    // Only the first base layer needs to be added to the map - all the others are accessed via the layer switcher.
    if (i === 0) {
      this.lMap.addLayer(base_layer);
    }

    // Initialise the Layer Control, if not yet.
    if (this.layer_control == null) {
      this.initialise_layer_control();
    } else {
      // Add the new base layer to layer_control.
      this.layer_control.addBaseLayer(base_layer, key);
    }
  };

  /**
   * Adds a Specific Layer and related Overlay to the Leaflet Map.
   *
   * @param {string} label - The Overlay Layer Label
   * @param layer - The Leaflet Overlayer
   * @param {bool} disabled_layer - The flag to disable the Layer from the Over Layers Control.
   */
  Drupal.Leaflet.prototype.add_overlay = function(label, layer, disabled_layer) {
    if (!disabled_layer) {
      this.lMap.addLayer(layer);
    }

    // Add the Overlay to the Drupal.Leaflet object.
    this.overlays[label] = layer;

    // Initialise the Layer Control, if it is not.
    if (label && this.layer_control == null) {
      this.initialise_layer_control();
    }

    // Add the Overlay to the Layer Control only if there is a Label.
    if (label && this.layer_control) {
      // If we already have a layer control, add the new overlay to it.
      this.layer_control.addOverlay(layer, label);
    }

  };

  Drupal.Leaflet.prototype.add_features = function(features, initial) {
    // Define Map Layers holder.
    let layers = {};

    for (let i = 0; i < features.length; i++) {
      let feature = features[i];
      let lFeature;

      // dealing with a layer group
      if (feature.group) {
        // Define a named Layer Group
        layers[feature['group_label']] = this.create_feature_group();
        const lGroup = this.create_feature_group();
        for (let groupKey in feature.features) {
          let groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            if (lFeature.setStyle) {
              this.feature_path_set_style(lFeature, groupFeature);
            }

            // Eventually add Popup to the lFeature.
            this.feature_bind_popup(lFeature, groupFeature);

            // Add the lFeature to the lGroup.
            layers[feature['group_label']].addLayer(lFeature);
          }
        }

        // Add the group to the layer switcher.
        if (feature.group_label) {
          this.add_overlay(feature.group_label, layers[feature['group_label']], feature['disabled']);
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

          // Eventually add Popup to the lFeature.
          this.feature_bind_popup(lFeature, feature);

          // Add the Leaflet Feature to the Map.
          this.lMap.addLayer(lFeature);
        }
      }

      // Allow others to do something with the feature that was just added to the map.
      $(document).trigger('leaflet.feature', [lFeature, feature, this]);
    }

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

  Drupal.Leaflet.prototype.create_feature_group = function() {
    return new L.featureGroup();
  };

  /**
   * Add Leaflet Popup to the Leaflet Feature.
   *
   * @param lFeature
   *   The Leaflet Feature
   * @param feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.feature_bind_popup = function(lFeature, feature) {
    if (feature.popup) {
      const popup_options = feature.popup.options ? JSON.parse(feature.popup.options) : {};
      lFeature.bindPopup(feature.popup.value, popup_options);
    }
  };

  /**
   * Add Leaflet Tooltip to the Leaflet Feature.
   * @param lFeature
   *   The Leaflet Feature
   * @param feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.feature_bind_tooltip = function(lFeature, feature) {
    // Set the Leaflet Tooltip, with its options (if the stripped value is not null).
    if (feature.tooltip && $(feature.tooltip.value).text().trim()) {
      const tooltip_options = feature.tooltip.options ? JSON.parse(feature.tooltip.options) : {};
      tooltip_options.offset = tooltip_options.offset ?? [0, feature.icon.iconSize ? -feature.icon.iconSize.y/1.5: 0];
      lFeature.bindTooltip(feature.tooltip.value, tooltip_options).openTooltip()
    }
  };

  /**
   * Add Leaflet Tooltip to the Leaflet Feature.
   * @param lFeature
   *   The Leaflet Feature
   * @param feature
   *   The Feature coming from Drupal settings.
   */
  Drupal.Leaflet.prototype.feature_path_set_style = function(lFeature, feature) {
    const lFeature_path_style = feature.path ? (feature.path instanceof Object ? feature.path : JSON.parse(feature.path)) : {};
    lFeature.setStyle(lFeature_path_style);
  };

  Drupal.Leaflet.prototype.create_feature = function(feature) {
    let lFeature;
    switch (feature.type) {
      case 'point':
        lFeature = this.create_point(feature);
        this.bounds.push([feature.lat, feature.lon]);
        break;

      case 'linestring':
        lFeature = this.create_linestring(feature, this.map_settings['leaflet_markercluster']['include_path']);
        this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
        break;

      case 'polygon':
        lFeature = this.create_polygon(feature, this.map_settings['leaflet_markercluster']['include_path']);
        this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
        break;

      case 'multipolygon':
        lFeature = this.create_multipolygon(feature, this.map_settings['leaflet_markercluster']['include_path']);
        this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
        break;

      case 'multipolyline':
        lFeature = this.create_multipoly(feature, this.map_settings['leaflet_markercluster']['include_path']);
        this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
        break;

      case 'json':
        lFeature = this.create_json(feature.json, feature.events);
        break;

      case 'multipoint':
      case 'geometrycollection':
        lFeature = this.create_collection(feature);
        this.bounds.push(lFeature.getBounds().getSouthWest(), lFeature.getBounds().getNorthEast());
        break;

      default:
        return; // Crash and burn.
    }

    // Eventually add Tooltip to the lFeature.
    this.feature_bind_tooltip(lFeature, feature);

    // Eventually add Popup to the lFeature.
    this.feature_bind_popup(lFeature, feature);

    if (feature['entity_id']) {
      // Generate the markers object index based on entity id (and geofield
      // cardinality), and add the marker to the markers object.
      let entity_id = feature.entity_id;
      if (this.map_definition.geofield_cardinality && this.map_definition.geofield_cardinality !== 1) {
        let i = 0;
        while (this.markers[entity_id + '-' + i]) {
          i++;
        }
        this.markers[entity_id + '-' + i] = lFeature;
        this.features[entity_id + '-' + i] = feature;
      }
      else {
        this.markers[entity_id] = lFeature;
        this.features[entity_id] = feature;
      }
    }
    return lFeature;
  };

  Drupal.Leaflet.prototype.create_layer = function(layer, key) {
    let self = this;
    let map_layer = new L.TileLayer(layer.urlTemplate);
    if (layer.type === 'wms') {
      map_layer = new L.tileLayer.wms(layer.urlTemplate, layer.options);
    }
    map_layer._leaflet_id = key;

    if (layer.options) {
      for (let option in layer.options) {
        map_layer.options[option] = layer.options[option];
      }
    }

    // Layers served from TileStream need this correction in the y coordinates.
    // TODO: Need to explore this more and find a more elegant solution.
    if (layer.type === 'tilestream') {
      map_layer.getTileUrl = function(tilePoint) {
        self._adjustTilePoint(tilePoint);
        let zoom = self._getZoomForUrl();
        return L.Util.template(self._url, L.Util.extend({
          s: self._getSubdomain(tilePoint),
          z: zoom,
          x: tilePoint.x,
          y: Math.pow(2, zoom) - tilePoint.y - 1
        }, self.options));
      }
    }
    return map_layer;
  };

  Drupal.Leaflet.prototype.create_icon = function(options) {
    let icon_options = {
      iconUrl: options.iconUrl,
    };

    // Override applicable marker defaults.
    if (options.iconSize) {
      icon_options.iconSize = new L.Point(parseInt(options.iconSize.x), parseInt(options.iconSize.y));
    }
    if (options.iconAnchor && options.iconAnchor.x && options.iconAnchor.y) {
      icon_options.iconAnchor = new L.Point(parseInt(options.iconAnchor.x), parseInt(options.iconAnchor.y));
    }
    if (options.popupAnchor && options.popupAnchor.x && options.popupAnchor.y) {
      icon_options.popupAnchor = new L.Point(parseInt(options.popupAnchor.x), parseInt(options.popupAnchor.y));
    }
    if (options.shadowUrl) {
      icon_options.shadowUrl = options.shadowUrl;
    }
    if (options.iconRetinaUrl) {
      icon_options.iconRetinaUrl = options.iconRetinaUrl;
    }
    if (options.shadowSize && options.shadowSize.x && options.shadowSize.y) {
      icon_options.shadowSize = new L.Point(parseInt(options.shadowSize.x), parseInt(options.shadowSize.y));
    }
    if (options.shadowAnchor && options.shadowAnchor.x && options.shadowAnchor.y) {
      icon_options.shadowAnchor = new L.Point(parseInt(options.shadowAnchor.x), parseInt(options.shadowAnchor.y));
    }
    if (options.className) {
      icon_options.className = options.className;
    }

    return new L.Icon(icon_options);
  };

  Drupal.Leaflet.prototype.create_divicon = function (options) {
    let html_class = options.html_class || '';
    let icon = new L.DivIcon({html: options.html, className: html_class});

    // override applicable marker defaults
    if (options.iconSize) {
      icon.options.iconSize = new L.Point(parseInt(options.iconSize.x, 10), parseInt(options.iconSize.y, 10));
    }
    if (options.iconAnchor && options.iconAnchor.x && options.iconAnchor.y) {
      icon.options.iconAnchor = new L.Point(parseInt(options.iconAnchor.x), parseInt(options.iconAnchor.y));
    }
    if (options.popupAnchor && !isNaN(options.popupAnchor.x) && !isNaN(options.popupAnchor.y)) {
      icon.options.popupAnchor = new L.Point(parseInt(options.popupAnchor.x), parseInt(options.popupAnchor.y));
    }

    return icon;
  };

  Drupal.Leaflet.prototype.create_point = function(marker) {
    let latLng = new L.LatLng(marker.lat, marker.lon);
    let lMarker;
    let marker_title = marker.title ? marker.title.replace(/<[^>]*>/g, '').trim() : '';
    let options = {
      title: marker_title,
      className: marker.className || '',
      alt: marker_title,
    };

    lMarker = new L.Marker(latLng, options);

    if (marker.icon) {
      if (marker.icon.iconType && marker.icon.iconType === 'html' && marker.icon.html) {
        let icon = this.create_divicon(marker.icon);
        lMarker.setIcon(icon);
      }
      else if (marker.icon.iconType && marker.icon.iconType === 'circle_marker') {
        try {
          options = marker.icon.options ? JSON.parse(marker.icon.options) : {};
          options.radius = options.radius ? parseInt(options['radius']) : 10;
        }
        catch (e) {
          options = {};
        }
        lMarker = new L.CircleMarker(latLng, options);
      }
      else if (marker.icon.iconUrl) {
        marker.icon.iconSize = marker.icon.iconSize || {};
        marker.icon.iconSize.x = marker.icon.iconSize.x || this.naturalWidth;
        marker.icon.iconSize.y = marker.icon.iconSize.y || this.naturalHeight;
        if (marker.icon.shadowUrl) {
          marker.icon.shadowSize = marker.icon.shadowSize || {};
          marker.icon.shadowSize.x = marker.icon.shadowSize.x || this.naturalWidth;
          marker.icon.shadowSize.y = marker.icon.shadowSize.y || this.naturalHeight;
        }
        let icon = this.create_icon(marker.icon);
        lMarker.setIcon(icon);
      }
    }

    return lMarker;
  };

  Drupal.Leaflet.prototype.create_linestring = function(polyline, clusterable = false) {
    let latlngs = [];
    for (let i = 0; i < polyline.points.length; i++) {
      let latlng = new L.LatLng(polyline.points[i].lat, polyline.points[i].lon);
      latlngs.push(latlng);
    }
    return clusterable ? new L.PolylineClusterable(latlngs) : new L.Polyline(latlngs);
  };

  Drupal.Leaflet.prototype.create_collection = function(collection) {
    let layers = new L.featureGroup();
    for (let x = 0; x < collection.component.length; x++) {
      layers.addLayer(this.create_feature(collection.component[x]));
    }
    return layers;
  };

  Drupal.Leaflet.prototype.create_polygon = function(polygon, clusterable = false) {
    let latlngs = [];
    for (let i = 0; i < polygon.points.length; i++) {
      let latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
      latlngs.push(latlng);
    }
    return clusterable ? new L.PolygonClusterable(latlngs) : new L.Polygon(latlngs);
  };

  Drupal.Leaflet.prototype.create_multipolygon = function(multipolygon, clusterable = false) {
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
    return clusterable ? new L.PolygonClusterable(polygons) : new L.Polygon(polygons);
  };

  Drupal.Leaflet.prototype.create_multipoly = function(multipoly, clusterable = false) {
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
    if (multipoly.multipolyline) {
      return clusterable ? new L.PolylineClusterable(polygons) : new L.Polyline(polygons);
    }
    else {
      return clusterable ? new L.PolygonClusterable(polygons) : new L.Polygon(polygons);
    }
  };

  Drupal.Leaflet.prototype.create_json = function(json, events) {
    let lJSON = new L.GeoJSON();

    lJSON.options.onEachFeature = function(feature, layer) {
      for (let layer_id in layer._layers) {
        for (let i in layer._layers[layer_id]._latlngs) {
        }
      }
      if (feature.properties.style) {
        layer.setStyle(feature.properties.style);
      }
      if (feature.properties.leaflet_id) {
        layer._leaflet_id = feature.properties.leaflet_id;
      }

      // Eventually add Popup to the Layer.
      this.feature_bind_popup(layer, feature.properties);

      for (e in events) {
        let layerParam = {};
        layerParam[e] = eval(events[e]);
        layer.on(layerParam);
      }
    };

    lJSON.addData(json);
    return lJSON;
  };

  // Set Map initial map position and Zoom.  Different scenarios:
  //  1)  Force the initial map center and zoom to values provided by input settings
  //  2)  Fit multiple features onto map using Leaflet's fitBounds method
  //  3)  Fit a single polygon onto map using Leaflet's fitBounds method
  //  4)  Display a single marker using the specified zoom
  //  5)  Adjust the initial zoom using zoomFiner, if specified
  //  6)  Cater for a map with no features (use input settings for Zoom and Center, if supplied)
  //
  // @NOTE: This method used by Leaflet Markecluster module (don't remove/rename)
  Drupal.Leaflet.prototype.fitBounds = function() {
    let start_zoom = this.map_settings.zoom ? this.map_settings.zoom : 12;
    // Note: this.map_settings.center might not be defined in case of Leaflet widget and Automatically locate user current position.
    let start_center = this.map_settings.center ? new L.LatLng(this.map_settings.center.lat, this.map_settings.center.lon) : new L.LatLng(0,0);

    //  Check whether the Zoom and Center are to be forced to use the input settings
    if (this.map_settings.map_position_force) {
      //  Set the Zoom and Center to values provided by the input settings
      this.lMap.setView(start_center, start_zoom);
    } else {
      if (this.bounds.length === 0) {
        //  No features - set the Zoom and Center to values provided by the input settings, if specified
        this.lMap.setView(start_center, start_zoom);
      } else {
        //  Set the Zoom and Center by using the Leaflet fitBounds function
        let bounds = new L.LatLngBounds(this.bounds);
        this.lMap.fitBounds(bounds);
        start_center = bounds.getCenter();
        start_zoom = this.lMap.getBoundsZoom(bounds);

        if (this.bounds.length === 1) {
          //  Single marker - set zoom to input settings
          this.lMap.setZoom(this.map_settings.zoom);
          start_zoom = this.map_settings.zoom;
        }
      }

      // In case of map initial position not forced, and zooFiner not null/neutral,
      // adapt the Map Zoom and the Start Zoom accordingly.
      if (this.map_settings.hasOwnProperty('zoomFiner') && parseInt(this.map_settings.zoomFiner)) {
        start_zoom += parseFloat(this.map_settings.zoomFiner);
        this.lMap.setView(start_center, start_zoom);
      }

      // Set the map start zoom and center.
      this.start_zoom = start_zoom;
      this.start_center = start_center;
    }

  };

  Drupal.Leaflet.prototype.map_reset = function(mapid) {
    Drupal.Leaflet[mapid].lMap.setView(Drupal.Leaflet[mapid].start_center, Drupal.Leaflet[mapid].start_zoom);
  };

  Drupal.Leaflet.prototype.map_reset_control = function(controlDiv, mapid) {
    let reset_map_control_settings = drupalSettings.leaflet[mapid].map.settings['reset_map'];
    let control = new L.Control({position: reset_map_control_settings.position});
    control.onAdd = function() {
      // Set CSS for the control border.
      let controlUI = L.DomUtil.create('div','resetzoom');
      controlUI.style.backgroundColor = '#fff';
      controlUI.style.border = '2px solid #fff';
      controlUI.style.borderRadius = '3px';
      controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
      controlUI.style.cursor = 'pointer';
      controlUI.style.margin = '6px';
      controlUI.style.textAlign = 'center';
      controlUI.title = Drupal.t('Click to reset the map to its initial state');
      controlUI.id = 'leaflet-map--' + mapid + '--reset-control';
      controlUI.disabled = true;
      controlDiv.appendChild(controlUI);

      // Set CSS for the control interior.
      let controlText = document.createElement('div');
      controlText.style.color = 'rgb(25,25,25)';
      controlText.style.fontSize = '1.1em';
      controlText.style.lineHeight = '28px';
      controlText.style.paddingLeft = '5px';
      controlText.style.paddingRight = '5px';
      controlText.innerHTML = Drupal.t('Reset Map');
      controlUI.appendChild(controlText);

      L.DomEvent
        .disableClickPropagation(controlUI)
        .addListener(controlUI, 'click', function() {
          Drupal.Leaflet.prototype.map_reset(mapid);
        },controlUI);
      return controlUI;
    };
    return control;
  };

  // Extend the L.Polyline to make it clustarable.
  // @see https://gis.stackexchange.com/questions/197882/is-it-possible-to-cluster-polygons-in-leaflet
  L.PolylineClusterable = L.Polyline.extend({
    _originalInitialize: L.Polyline.prototype.initialize,

    initialize: function (bounds, options) {
      this._originalInitialize(bounds, options);
      this._latlng = this.getBounds().getCenter();
    },

    getLatLng: function () {
      return this._latlng;
    },

    setLatLng: function () {}
  });

  // Extend the L.Polygon to make it clustarable.
  // @see https://gis.stackexchange.com/questions/197882/is-it-possible-to-cluster-polygons-in-leaflet
  L.PolygonClusterable = L.Polygon.extend({
    _originalInitialize: L.Polygon.prototype.initialize,

    initialize: function (bounds, options) {
      this._originalInitialize(bounds, options);
      this._latlng = this.getBounds().getCenter();
    },

    getLatLng: function () {
      return this._latlng;
    },

    setLatLng: function () {}
  });

})(jQuery, Drupal, drupalSettings);
