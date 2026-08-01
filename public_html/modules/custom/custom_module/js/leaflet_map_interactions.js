(function ($, Drupal, once, drupalSettings) {

  'use strict';

  Drupal.behaviors.mapResizeLeafletMapOnContainerChange = {
    attach(context) {
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        const map = lMap;
        const elements = once(
          mapid + '-init',
          context.querySelector('#' + mapid)
        );
        const resizeObserver = new ResizeObserver(() => {
          if (map) {
            map.invalidateSize();
          }
        });
        elements.forEach(function(element) {
          resizeObserver.observe(element);
        })
      })
    }
  };

  /**
   * Leaflet interactions behavior.
   */
  Drupal.behaviors.leafletInteractions = {

    // Default zoom level for icon sizing.
    zoomDefaultIconSize: 19,

    // Zoom increment applied when clicking a Point permanent Tooltip.
    tooltipClickZoomIncrement: 3,

    // Stores markers that are currently hidden.
    hidden_markers: [],

    /**
     * Attaches the behavior to the context.
     */
    attach: function(context) {
      const self = this;

      // React on leafletMapInit event.
      // Resizing Markers.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        const map = lMap;
        const markers = Drupal.Leaflet?.[mapid]?.markers || {};
        const features = Drupal.Leaflet?.[mapid]?.features || {};
        const markersOriginalSizes = self.setMarkersOriginalSizes(markers);

        Drupal.Leaflet[mapid].imagesZoomLimit = 16;

        // Trigger/Process Initial Actions.
        self.processInitialActions(mapid, map, features, markers, markersOriginalSizes);

        // Set Actions on every Zoom End.
        map.on('zoomend', function () {
          // Markers resize on Zoomend.
          self.markersResizeOnZoomEnd(mapid, map, features, markers, markersOriginalSizes);

          // Set Tooltip Visibility, depending on Map Zoom.
          self.setPermanentTooltipVisibility(mapid, map);

          // (Re)bind the Click-to-Zoom action on Permanent Tooltips, as their
          // DOM element gets recreated by setPermanentTooltipVisibility.
          self.bindPermanentTooltipClickZoom(mapid, map);
        });

        // `fullscreenchange` Event that's fired when entering or exiting fullscreen.
        map.on('fullscreenchange', function () {
          // Toggle header z-index based on fullscreen state.
          $("header.site-header").css('z-index', map.isFullscreen() ? 1 : 101);
        });
      });

      // Interact with each feature created and added to the map.
      $(context).on('leaflet.feature', function(e, lFeature, feature, add_features, layers_groups = null) {
        if (!feature) {
          return;
        }

        // Add Arrows effect to Paths.
        if (feature.type !== 'point' && feature.path) {
          const feature_path = feature.path instanceof Object ? feature.path : JSON.parse(feature.path);
          if (feature_path['arrowed'] === "1" && typeof lFeature.arrowheads !== "undefined") {
            const feature_path_weight = Number(feature_path.weight) ?? 1;
            const arrow_size =  3 * feature_path.weight;
            lFeature.arrowheads({frequency: '100px', size: arrow_size + '%', proportionalToTotal: true});
          }
        }
        // Pulsing Markers.
        else if (feature.properties && feature.properties.length > 0) {
          try {
            const properties = JSON.parse(feature.properties);

            // Check if feature should have a pulsing marker.
            if (properties['active_status'] &&
              parseInt(properties['active_status']) === 1 &&
              properties['active_type'] &&
              properties['active_type'].length > 0) {

              const pulsing_type = properties['active_type'];
              // Define a pulsing_marker L.Marker as property attached to the
              // feature.
              feature.pulsing_marker = new L.Marker(new L.LatLng(feature.lat, feature.lon), {
                icon: L.divIcon({
                  className: pulsing_type + ' pulsing-marker',
                  html: '<span class="pulsing-marker dot"></span>',
                }),
                iconSize: [100, 100]
              });

              // Add the feature pulsing marker to the map.
              Drupal.Leaflet.prototype.feature_bind_popup(feature.pulsing_marker, feature);

              // Add the feature.pulsing_marker to appropriate layer group.
              if (layers_groups?.unclustered &&
                feature.group_label &&
                layers_groups.unclustered.hasOwnProperty(feature.group_label)) {
                feature.pulsing_marker.addTo(layers_groups.unclustered[feature.group_label]);
              }
              else if (layers_groups &&
                feature.group_label &&
                layers_groups.hasOwnProperty(feature.group_label)) {
                feature.pulsing_marker.addTo(layers_groups[feature.group_label]);
              }
              else if (add_features?.lMap) {
                feature.pulsing_marker.addTo(add_features.lMap);
              }
            }
          } catch (error) {
            console.warn('Error parsing feature properties:', error);
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

    /**
     * Process all Initial Action.
     *
     * @param {string} mapid
     *   The map ID.
     * @param {object} map
     *   The Leaflet map object.
     * @param {object} features
     *   The features collection.
     * @param {object} markers
     *   The markers collection.
     * @param {object} markersOriginalSizes
     *   Original sizes of markers.
     */
    processInitialActions: function(mapid, map, features, markers, markersOriginalSizes) {

      // Set hover scale effects on initially rendered Map Markers.
      for (let i in markers) {
        this.setScalingOnMarkerHovering(markers[i]);
      }

      // Trigger an Initial React on Zoom End function.
      this.markersResizeOnZoomEnd(mapid, map, features, markers, markersOriginalSizes);

      // Ajax Import all Markers Popups.
      this.ajaxImportAllMarkersPopups(markers);

      // Set Permanent Tooltip Visibility.
      this.setPermanentTooltipVisibility(mapid, map);

      // Bind the Click-to-Zoom action on Permanent Tooltips.
      this.bindPermanentTooltipClickZoom(mapid, map);
    },

    /**
     * Set Visual Scaling on Marker Hovering.
     *
     * @param {object} marker
     *   The marker.
     */
    setScalingOnMarkerHovering: function(marker) {
      if (marker._icon) {
        let icon = marker._icon;
        icon.style.transition = 'transform 180ms cubic-bezier(.34,1.56,.64,1)';
        icon.addEventListener('mouseenter', () => {
          icon.style.transformOrigin = 'center center';
          icon.style.transform += ' scale(1.2)';
        });
        icon.addEventListener('mouseleave', () => {
          icon.style.transform = icon.style.transform.replace(' scale(1.2)', '');
        });
      }
    },

    /**
     * Import all marker popups via AJAX.
     *
     * @param {object} markers
     *   The markers collection.
     */
    ajaxImportAllMarkersPopups: function(markers) {
      if (!markers) {
        return;
      }

      for (const i in markers) {
        if (!markers.hasOwnProperty(i)) {
          continue;
        }

        const popup = markers[i].getPopup();
        if (!popup || !popup._source || !popup._source._popup) {
          continue;
        }

        const element = document.createElement('div');
        element.innerHTML = popup._source._popup._content;
        const content = $('[data-leaflet-ajax-popup]', element);

        if (content.length) {
          const url = content.data('leaflet-ajax-popup');

          $.get({url: url}).done(function (response) {
            // Not super clear where data is coming from (when),
            // so we check all the following responses indexes.
            let data = null;

            if (response[1] && response[1].command === "insert") {
              data = response[1].data;
            } else if (response[2] && response[2].command === "insert") {
              data = response[2].data;
            }

            if (data) {
              popup.setContent(data);
            }
          }).fail(function(jqXHR, textStatus, errorThrown) {
            console.warn('Failed to load popup content:', textStatus, errorThrown);
          });
        }
      }
    },

    /**
     * Update Icon Size.
     *
     * @param {string} i
     *   The marker index.
     * @param {object} marker
     *   The marker object.
     * @param {number} iconSizeRate
     *   The icon size rate.
     * @param {object} markersOriginSizes
     *   Original sizes of markers.
     */
    updateIconSize: function(i, marker, iconSizeRate, markersOriginSizes) {
      if (!marker || !marker.options || !marker.options.icon || !markersOriginSizes[i]) {
        return;
      }

      const icon = marker.options.icon;
      if (!icon.options || !icon.options.iconSize) {
        return;
      }

      icon.options.iconSize.x = Math.ceil(markersOriginSizes[i].x * iconSizeRate);
      icon.options.iconSize.y = Math.ceil(markersOriginSizes[i].y * iconSizeRate);
      marker.setIcon(icon);
    },

    /**
     * Get IconSizeRate.
     *
     * @param {number} zoomLevel
     *   The current zoom level.
     * @returns {number}
     *   The icon size rate.
     */
    getIconSizeRate: function(zoomLevel) {
      return Math.pow(zoomLevel / this.zoomDefaultIconSize, 3);
    },

    /**
     * Set MarkersOriginalSizes.
     *
     * @param {object} markers
     *   The markers collection.
     * @returns {object}
     *   The original marker sizes.
     */
    setMarkersOriginalSizes: function(markers) {
      const markersOriginalSizes = {};

      for (const i in markers) {
        if (markers.hasOwnProperty(i) &&
          markers[i] &&
          !markers[i].setStyle &&
          markers[i].options &&
          markers[i].options.icon &&
          markers[i].options.icon.options &&
          markers[i].options.icon.options.iconSize) {

          markersOriginalSizes[i] = {
            x: markers[i].options.icon.options.iconSize.x,
            y: markers[i].options.icon.options.iconSize.y,
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
     * @param {string} mapid
     *   The map ID.
     * @param {object} map
     *   The Leaflet map object.
     * @param {object} features
     *   The features collection.
     * @param {object} markers
     *   The markers collection.
     * @param {object} markersOriginalSizes
     *   Original sizes of markers.
     */
    markersResizeOnZoomEnd: function(mapid, map, features, markers, markersOriginalSizes) {
      if (!map || !features || !markers || !markersOriginalSizes) {
        return;
      }

      const self = this;
      const zoomLevel = map.getZoom();
      const iconSizeRate = this.getIconSizeRate(zoomLevel);

      for (const i in markers) {
        if (!markers.hasOwnProperty(i) || !markers[i]) {
          continue;
        }

        const hidden_marker_index = self.hidden_markers.indexOf(i);

        // Update Icon size, only in case of Points (setStyle undefined).
        if (!markers[i].setStyle) {
          this.updateIconSize(i, markers[i], iconSizeRate, markersOriginalSizes);
        }

        // Set Feature visibility, if properties are available.
        if (features[i] && features[i].properties && features[i].properties.length > 0) {
          try {
            const properties = JSON.parse(features[i].properties);

            // Handle zoom visibility range.
            if (properties['zoom_visibility_range']) {
              const visibility_range = properties['zoom_visibility_range'].split("-");
              const minZoom = parseInt(visibility_range[0], 10);
              const maxZoom = parseInt(visibility_range[1], 10);

              // Check if marker should be hidden or shown based on zoom level.
              if (zoomLevel <= minZoom || zoomLevel > maxZoom) {

                // Remove the Marker from the Group Overlay.
                if (markers[i].options &&
                  markers[i].options.group_label &&
                  Drupal.Leaflet[mapid] &&
                  Drupal.Leaflet[mapid].overlays &&
                  Drupal.Leaflet[mapid].overlays[markers[i].options.group_label] &&
                  Drupal.Leaflet[mapid].overlays[markers[i].options.group_label]._layers.hasOwnProperty(markers[i]._layers_id)) {

                  Drupal.Leaflet[mapid].overlays[markers[i].options.group_label].removeLayer(markers[i]);

                  // In case the features[i] has a pulsing_marker attached.
                  if (features[i].pulsing_marker) {
                    // Remove the pulsing_marker from the Group Overlay.
                    Drupal.Leaflet[mapid].overlays[markers[i].options.group_label].removeLayer(features[i].pulsing_marker);
                  }
                }
                // Otherwise Remove the marker directly from the map.
                else {
                  map.removeLayer(markers[i]);

                  // In case the features[i] has a pulsing_marker attached.
                  if (features[i].pulsing_marker) {
                    // Remove also the pulsing_marker from map.
                    map.removeLayer(features[i].pulsing_marker);
                  }
                }

                // Eventually remove the Marker from its Group Overlay cluster.
                const overlays = Drupal.Leaflet[mapid].overlays[markers[i].options.group_label];
                if (overlays && overlays._layers) {
                  for (const k in overlays._layers) {
                    if (Object.prototype.hasOwnProperty.call(overlays._layers, k) &&
                      overlays._layers[k] &&
                      overlays._layers[k]._markerCluster) {
                      overlays._layers[k].removeLayer(markers[i]);

                      // In case the features[i] has a pulsing_marker attached.
                      if (features[i].pulsing_marker) {
                        // Remove also the pulsing_marker from the Group Overlay
                        // cluster.
                        overlays._layers[k].removeLayer(features[i].pulsing_marker);
                      }
                    }
                  }
                }

                // Add the hidden marker in the hidden_markers list/array.
                if (hidden_marker_index === -1) {
                  self.hidden_markers.push(i);
                }
              }
              // Otherwise if the marker is hidden, add it to the map.
              else if (hidden_marker_index > -1) {
                // In case the marker is part of a Group Overlay.
                if (markers[i].options &&
                  markers[i].options.group_label &&
                  Drupal.Leaflet[mapid] &&
                  Drupal.Leaflet[mapid].overlays &&
                  Drupal.Leaflet[mapid].overlays[markers[i].options.group_label]) {

                  // Add the marker in its group Overlay.
                  Drupal.Leaflet[mapid].overlays[markers[i].options.group_label].addLayer(markers[i]);

                  // In case the features[i] has a pulsing_marker attached.
                  if (features[i].pulsing_marker) {
                    // Add also the pulsing_marker in its group Overlay.
                    Drupal.Leaflet[mapid].overlays[markers[i].options.group_label].addLayer(features[i].pulsing_marker);
                  }
                }
                // Otherwise add it directly to the map.
                else {
                  markers[i].addTo(map);
                  if (features[i].pulsing_marker) {
                    features[i].pulsing_marker.addTo(map);
                  }
                }

                // In case the features[i] has a pulsing_marker attached.
                if (features[i].pulsing_marker) {
                  // Add also the pulsing_marker from map.
                  Drupal.Leaflet.prototype.feature_bind_popup(features[i].pulsing_marker, features[i]);
                }

                // Add the hover scaling effect to the Map marker.
                self.setScalingOnMarkerHovering(markers[i]);

                // Eventually re-add the Marker in its Group Overlay Cluster.
                const overlays = Drupal.Leaflet[mapid].overlays[markers[i].options.group_label];
                if (overlays && overlays._layers) {
                  for (const k in overlays._layers) {
                    if (Object.prototype.hasOwnProperty.call(overlays._layers, k) &&
                      overlays._layers[k] &&
                      overlays._layers[k]._markerCluster) {
                      overlays._layers[k].addLayer(markers[i]);
                    }
                  }
                }

                // Remove the marker from hidden markers.
                self.hidden_markers.splice(hidden_marker_index, 1);
              }
            }
          } catch (error) {
            console.warn('Error parsing feature properties in markersResizeOnZoomEnd:', error);
          }
        }
      }

      // Set specific Overlays Visibility, depending on Map Zoom.
      const overlays = Drupal.Leaflet[mapid].overlays;

      // Remove specific zoom visibility of Areas and quarters, because now
      // managed by Zoom Visibility taxonomy.
      // Zone e Quartieri.
      /* if (overlays["Zone e Quartieri"]) {
          if (zoomLevel > 13) {
            overlays["Zone e Quartieri"].remove();
          } else {
            overlays["Zone e Quartieri"].addTo(map);
          }
        }*/

      // Areas and quarters.
      /* if (overlays["Areas and quarters"]) {
          if (zoomLevel > 13) {
            overlays["Areas and quarters"].remove();
          } else {
            overlays["Areas and quarters"].addTo(map);
          }
        }*/

      // Handle Images/Immagini overlay visibility based on zoom and user preference
      const imagesOverlayActive = sessionStorage.getItem('imagesOverlayActive');
      const images_overlays = ['Images', 'Immagini'];
      images_overlays.forEach(function(value) {
        if (overlays[value] && Drupal.Leaflet[mapid].imagesZoomLimit) {
          if (zoomLevel < Drupal.Leaflet[mapid].imagesZoomLimit) {
            // Always hide at low zoom levels regardless of preference
            if (map.hasLayer(overlays[value])) {
              // Use removeLayer instead of remove() to properly trigger the overlayremove event
              map.removeLayer(overlays[value]);
            }
          } else if (imagesOverlayActive === null || imagesOverlayActive === '1') {
            // Show only if preference is null (not set) or explicitly set to '1'
            if (!map.hasLayer(overlays[value])) {
              // Use addLayer instead of addTo() to properly trigger the overlayadd event
              map.addLayer(overlays[value]);
            }
          } else {
            // Hide if preference is '0'
            if (map.hasLayer(overlays[value])) {
              map.removeLayer(overlays[value]);
            }
          }
        }
      })
    },

    /**
     * Set Permanent Tooltip Visibility, depending on Map Zoom.
     *
     * @param {string} mapid
     *   The map ID.
     * @param {object} map
     *   The Leaflet map object.
     */
    setPermanentTooltipVisibility: function(mapid, map) {
      if (!map || !Drupal.Leaflet || !Drupal.Leaflet[mapid]) {
        return;
      }

      // Specific Permanent Tooltip visibility on Zoom end.
      const zoomLevel = map.getZoom();
      const permanent_tooltip_features = Drupal.Leaflet[mapid].permanent_tooltip_features || [];
      // Set permanent tooltips only between a specific Zoom Range.
      const isPermanent = zoomLevel > 6 && zoomLevel <= 17;

      for (const permanent_tooltip_feature of permanent_tooltip_features) {
        if (!permanent_tooltip_feature) {
          continue;
        }

        const tooltip = permanent_tooltip_feature.getTooltip();
        if (!tooltip) {
          continue;
        }

        tooltip.options.permanent = isPermanent;
        permanent_tooltip_feature.bindTooltip(tooltip.getContent(), tooltip.options);
      }
    },

    /**
     * Bind a Click-to-Zoom action to all Permanent Tooltips.
     *
     * Clicking a permanently visible Tooltip (e.g. "Taranto - Old Town" or
     * "Taranto - New Town") zooms the map in:
     * - for Point Features (Marker/CircleMarker, exposing getLatLng()), by
     *   tooltipClickZoomIncrement levels above the current Map Zoom,
     *   centered on the Point.
     * - for Line/Polygon Features (Polyline/Polygon/MultiPolygon, exposing
     *   getBounds()), to the Zoom level that fits the Feature bounds.
     *
     * Tooltip elements are recreated by setPermanentTooltipVisibility on
     * every zoomend, so this needs to be called again every time that
     * happens.
     *
     * @param {string} mapid
     *   The map ID.
     * @param {object} map
     *   The Leaflet map object.
     */
    bindPermanentTooltipClickZoom: function(mapid, map) {
      if (!map || !Drupal.Leaflet || !Drupal.Leaflet[mapid]) {
        return;
      }

      const self = this;
      const permanent_tooltip_features = Drupal.Leaflet[mapid].permanent_tooltip_features || [];

      for (const lFeature of permanent_tooltip_features) {
        if (!lFeature || !lFeature.getTooltip) {
          continue;
        }

        const tooltip = lFeature.getTooltip();
        if (!tooltip || !tooltip.options.permanent) {
          continue;
        }

        const tooltipElement = tooltip.getElement ? tooltip.getElement() : null;
        if (!tooltipElement) {
          continue;
        }

        // Discriminate Point Features (Marker/CircleMarker) from
        // Line/Polygon Features (Polyline/Polygon/MultiPolygon).
        const isPoint = typeof lFeature.getLatLng === 'function';
        const isBounded = !isPoint && typeof lFeature.getBounds === 'function';

        if (!isPoint && !isBounded) {
          continue;
        }

        once('leaflet-tooltip-click-zoom', tooltipElement).forEach(function (element) {
          // Permanent Tooltips are not interactive by default (pointer-events: none).
          element.style.pointerEvents = 'auto';
          element.style.cursor = 'pointer';
          element.addEventListener('click', function (e) {
            e.stopPropagation();
            let incrementedZoom = map.getZoom() + self.tooltipClickZoomIncrement;
            incrementedZoom = incrementedZoom <= 18 ? incrementedZoom : 18;
            if (isPoint) {
              map.setView(lFeature.getLatLng(), incrementedZoom);
            }
            else if (isBounded) {
              map.fitBounds(lFeature.getBounds());
            }
          });
        });
      }
    }

  };

})(jQuery, Drupal, once, drupalSettings);
