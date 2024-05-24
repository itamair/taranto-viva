(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.leafletInteractions = {
    attach: function(context) {
      const self = this;
      // Define a boolean leafletMapInit in the context, so not to perform same
      // bind actions more than ones
      context.leafletMapInit = false;

      // React on leafletMapInit event.
      // Resizing Markers.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        // If the leafletMapInit bidnn actions didn't run already.
        if (!context.leafletMapInit) {
          context.leafletMapInit = true;
          let map = lMap;
          const markers = Drupal.Leaflet[mapid].markers;
          const features = Drupal.Leaflet[mapid].features;
          const markersOriginalSizes = self.setMarkersOriginalSizes(markers);

          // Trigger/Process Initial Actions
          self.processInitialActions(mapid, map, features, markers, markersOriginalSizes);

          // Set Actions on every Zoom End
          map.on('zoomend', function () {
            // Markers resize on Zoomend.
            self.markersResizeOnZoomEnd(mapid, map, features, markers, markersOriginalSizes);
          });

          // `fullscreenchange` Event that's fired when entering or exiting fullscreen.
          map.on('fullscreenchange', function () {
            if (map.isFullscreen()) {
              // Set to 1 the zIndex of the header not to stay over the fullscreen Leaflet Map.
              $("header.site-header").css('z-index', 1);
            } else {
              // Set to 101 the zIndex of the header to stay over the fullscreen Leaflet Map.
              $("header.site-header").css('z-index', 101);
            }
          });

        }
      });

      // Interact with each feature created and added to the map.
      $(context).on('leaflet.feature', function(e, lFeature, feature, add_features, layers_groups = null) {

        // Add Arrows effect to Paths.
        if (feature.type !== 'point' && feature.path) {
          const feature_path = feature.path instanceof Object ? feature.path : JSON.parse(feature.path);
          if (feature_path['arrowed'] === "1" && typeof lFeature.arrowheads !== "undefined") {
            lFeature.arrowheads({size: '7%'});
          }
        }

        // Pulsing Markers.
        else if (feature['properties'] && feature['properties'].length > 0) {
          const properties = JSON.parse(feature['properties']);
          if ((properties['active_status'] && parseInt(properties['active_status']) === 1) && (properties['active_type'] && properties['active_type'].length > 0)) {
            const pulsing_type = properties['active_type'];
            const pulsing_marker = new L.Marker(new L.LatLng(feature.lat, feature.lon), {
              icon: L.divIcon({
                className: pulsing_type + ' pulsing-marker',
                html: '<span class="pulsing-marker dot"></span>',
              }),
              iconSize: [100, 100]
            })
            if (typeof pulsing_marker !== 'undefined') {
              Drupal.Leaflet.prototype.feature_bind_popup(pulsing_marker, feature);
              if (layers_groups && layers_groups.unclustered && feature.group_label && layers_groups.unclustered.hasOwnProperty(feature.group_label)) {
                pulsing_marker.addTo(layers_groups.unclustered[feature.group_label]);
              }
              else if (layers_groups && feature.group_label && layers_groups.hasOwnProperty(feature.group_label)) {
                pulsing_marker.addTo(layers_groups[feature.group_label]);
              }
              else {
                pulsing_marker.addTo(add_features.lMap);
              }
            }
          }
        }

        /*        // Add an event listener on the popup open to dynamically pan to the center.
                lFeature.on('popupopen', function (popup) {
                  const zoom = popup.target._map.getZoom();
                  const zoom_pow = Math.pow(zoom , 3);
                  const lat_addition = zoom < 17 ? 12/zoom_pow: 3/zoom_pow;
                  popup.target._map.panTo([popup.target._latlng.lat + lat_addition, popup.target._latlng.lng])
                });*/
      });
    },
    zoomDefaultIconSize: 18,
    hidden_markers: [],

    /**
     * Process all Initial Action.
     *
     * @param mapid
     * @param map
     * @param features
     * @param markers
     * @param markersOriginalSizes
     */
    processInitialActions: function(mapid, map, features, markers, markersOriginalSizes) {
      const self = this;
      // Trigger an Initial React on Zoom End function.
      self.markersResizeOnZoomEnd(mapid, map, features, markers, markersOriginalSizes);
      // Ajax Import all Markers Popups.
      self.ajaxImportAllMarkersPopups(markers);
    },

    ajaxImportAllMarkersPopups: function(markers) {
      for (let i in markers) {
        let popup = markers[i].getPopup();
        if (popup) {
          let element = document.createElement('div');
          element.innerHTML = popup._source._popup._content;
          let content = $('[data-leaflet-ajax-popup]', element);
          if (content.length) {
            let url = content.data('leaflet-ajax-popup');
            $.get({'url': url}).done(function (response) {
              // Not super clear where data is coming from (when),
              // so we check all the following responses indexes.
              const data = response[1].command === "insert" ? response[1].data : (response[2].command === "insert" ? response[2].data : null);
              if (data) {
                popup.setContent(data);
              }
            })
          }
        }
      }
    },

    /**
     * Update Icon Size.
     *
     * @param i
     * @param marker
     * @param iconSizeRate
     * @param markersOriginSizes
     */
    updateIconSize: function(i, marker, iconSizeRate, markersOriginSizes) {
      let icon = marker.options.icon;
      icon.options.iconSize.x = Math.ceil( markersOriginSizes[i].x*iconSizeRate);
      icon.options.iconSize.y = Math.ceil( markersOriginSizes[i].y*iconSizeRate);
      //icon.options.iconAnchor = new L.Point(icon.options.iconSize.x/2, icon.options.iconSize.y);
      marker.setIcon(icon);
    },

    /**
     * Get IconSizeRate.
     *
     * @param zoomLevel
     * @returns {number}
     */
    getIconSizeRate: function(zoomLevel) {
      const self = this;
      //return Math.exp(Math.sqrt(map.getZoom()));
      return Math.pow(zoomLevel/self.zoomDefaultIconSize, 3);
    },

    /**
     * Set MarkersOriginalSizes.
     *
     * @param markers
     * @returns {{}}
     */
    setMarkersOriginalSizes: function(markers) {
      let markersOriginalSizes = {};
      for (let i in markers) {
        if (markers.hasOwnProperty(i) && !markers[i].setStyle) {
          let icon = markers[i].options.icon;
          markersOriginalSizes[i] = {
            x: icon.options.iconSize.x,
            y: icon.options.iconSize.y,
          };
        }
      }
      return markersOriginalSizes;
    },

    /**
     * Set React on Zoom End
     *
     * Updates Marker Icon Sizes and Show/Hide Features with Zoom Limits.
     *
     * @param mapid
     * @param map
     * @param features
     * @param markers
     * @param markersOriginalSizes
     */
    markersResizeOnZoomEnd: function(mapid, map, features, markers, markersOriginalSizes) {
      const self = this;
      const zoomLevel = map.getZoom();
      const iconSizeRate = this.getIconSizeRate(zoomLevel);
      for (let i in markers) {
        if (markers.hasOwnProperty(i)) {
          let hidden_marker_index = self.hidden_markers.indexOf(i);
          // Update Icon size, only in case of Points (setStyle undefined),
          if (!markers[i].setStyle) {
            this.updateIconSize(i, markers[i], iconSizeRate, markersOriginalSizes);
          }
          // Set Feature visibility, if properties['min_zoom_visibility'] is set.
          if (features.hasOwnProperty(i) && features[i] && features[i]['properties'] && features[i]['properties'].length > 0) {
            const properties = JSON.parse(features[i]['properties']);

/*            if (properties['min_zoom_visibility'] && zoomLevel <= properties['min_zoom_visibility']) {
              map.removeLayer(markers[i]);
              if (hidden_marker_index === -1) {
                self.hidden_markers.push(i);
              }
            }
            else if (markers.hasOwnProperty(i) && hidden_marker_index > -1) {
              markers[i].addTo(map);
              self.hidden_markers.splice(hidden_marker_index, 1);
            }*/

            // Set Feature Zoom Visibility Range, if properties['min_zoom_visibility'] is set.
            if (properties['zoom_visibility_range']) {
              const visibility_range = properties['zoom_visibility_range'].split("-");
              console.log(zoomLevel);
              if ( zoomLevel <= parseInt(visibility_range[0]) || zoomLevel > parseInt(visibility_range[1])) {
                map.removeLayer(markers[i]);
                if (markers[i].options['group_label'] && Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]) {
                  for (let k in Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers) {
                    if (markers[i].options['group_label'] && Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']] && Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers[k].hasOwnProperty('_markerCluster')) {
                      Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers[k].removeLayer(markers[i]);
                    }
                  }
                }
                if (hidden_marker_index === -1) {
                  self.hidden_markers.push(i);
                }
              }
              else if (markers.hasOwnProperty(i) && hidden_marker_index > -1) {
                markers[i].addTo(map);
                if (markers[i].options['group_label'] && Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]) {
                  for (let k in Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers) {
                    if (Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers[k].hasOwnProperty('_markerCluster')) {
                      Drupal.Leaflet[mapid].overlays[markers[i].options['group_label']]._layers[k].addLayer(markers[i]);
                    }
                  }
                }
                self.hidden_markers.splice(hidden_marker_index, 1);
              }
            }
          }
        }
      }
    }

  };

})(jQuery, Drupal);
