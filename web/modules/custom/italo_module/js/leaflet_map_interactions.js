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

  Drupal.behaviors.mapAddLeafletSidebarListControl = {
    attach(context) {
      const self = this;
      const root = context || document;
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        const map = lMap;
        const elements = once(
          mapid + '-init',
          context.querySelector('#' + mapid)
        );
        if (mapid === 'leaflet-map-view-geo-places-page-map-taranto-viva') {
          const leaflet_list_control_options = {
            'classes': mapid,
            'list': {
              'title': Drupal.t('Places of Interest'),
              'items': [],
              'start_collapsed': 0,
            }
          };
          self.addLeafletSidebarListControl(root, lMap, mapid, data_markers, leaflet_list_control_options);
        }
      })
    },

    /**
     * Add a choropleth legend to the map.
     *
     * @param {object} root
     *   The context || document object.
     * @param {L.Map} map
     *   The Leaflet map object.
     * @param {string} mapid
     *   The map ID.
     * @param {Object} settings
     *   The choropleth settings.
     */
    addLeafletSidebarListControl: function (root, map, mapid,markers, settings) {
      const self = this;
      const classes = settings.classes;

      // Create a Sidebar List control.
      const sidebarListControl = L.control({ position: 'topright' });

      sidebarListControl.onAdd = function (map) {
        const div = L.DomUtil.create('div', 'leaflet-control leaflet-sidebar-list' + ' ' + classes);

        // Create List Collapsed label.
        const listCollapsedLabel = L.DomUtil.create('div', 'list-collapsed-label', div);
        // Apply custom List Collapsed Label content.
        listCollapsedLabel.innerHTML = settings.list.title;

        // Add toggle button.
        const toggleBtn = L.DomUtil.create('div', 'list-toggle', div);
        toggleBtn.innerHTML = 'X';
        toggleBtn.title = 'Close List';

        // Create content container.
        const content = L.DomUtil.create('div', 'list-content', div);

        // Add title if provided.
        if (settings.list.title) {
          const title = L.DomUtil.create('div', 'list-title', content);
          title.innerHTML = settings.list.title;
        }

        // Add subtitle if provided.
        if (settings.list.subtitle) {
          const subtitle = L.DomUtil.create('div', 'list-subtitle', content);
          subtitle.innerHTML = settings.list.subtitle;
        }

        // Add List Items.
        const list_items = settings.list.items;

        const items = L.DomUtil.create('div', 'list-items', content);

        for (let i = 0; i < list_items.length; i++) {
          const item = L.DomUtil.create('div', 'list-item', items);
          item.innerHTML = list_items[i];
        }

        content.appendChild(items);

        // Check if legend should start collapsed based on settings.
        const startCollapsed = settings.list.start_collapsed || false;
        if (startCollapsed) {
          div.classList.add('list-collapsed');
        }

        // Add toggle functionality.
        self.setupLegendToggle(div, toggleBtn);

        // Prevent map zoom/scroll when scrolling inside the control.
        L.DomEvent.disableScrollPropagation(div);

        return div;
      };

      // Fetch Geoplaces data to populate the sidebar list items, then add the
      // control to the map.
      const pageLang = drupalSettings.path.currentLanguage;
      fetch(pageLang + '/taranto-viva-geoplaces-list')
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          settings.list.items = data.map(function (geoplace) {
            return geoplace.title;
          });
          sidebarListControl.addTo(map);
          self.addInteractivityWithSidebar(root, mapid, map, markers);
        })
        .catch(function (error) {
          console.warn('Failed to load Geoplaces list:', error);
        });
    },

    /**
     * Setup legend toggle functionality.
     *
     * @param {Element} legendDiv
     *   The legend div element.
     * @param {Element} toggleBtn
     *   The toggle button element.
     */
    setupLegendToggle: function (legendDiv, toggleBtn) {

      // Handle toggle button click (collapse legend).
      toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        legendDiv.classList.add('list-collapsed');
      });

      // Handle collapsed legend click (expand legend).
      legendDiv.addEventListener('click', function (e) {
        if (legendDiv.classList.contains('list-collapsed')) {
          e.stopPropagation();
          legendDiv.classList.remove('list-collapsed');
        }
      });

      // Prevent map interaction when clicking on the legend.
      L.DomEvent.disableClickPropagation(legendDiv);
    },

    /**
     * Add Interactivity with Sidebar Markers List.
     *
     * @param {object} root
     *   The context || document object.
     * @param {string} mapid
     *   The map ID.
     * @param {object} map
     *   The map object.
     * @param {obejct} markers
     *   The map markers object.
     */
    addInteractivityWithSidebar: function(root, mapid, map, markers) {
      const leaflet_sidebar_list_mapid_selector = '.leaflet-sidebar-list.' + mapid;

      if (
        mapid === "leaflet-map-view-geo-places-page-map-taranto-viva" &&
        (root.querySelector('.view-display-id-block_geoplaces_locations') || root.querySelector(leaflet_sidebar_list_mapid_selector))
      ) {

        let clickTimeout = null;
        let currentMarkerId = null;
        let popup = null;

        // Function to increment by 1 the MarkerId identifier, in case it is
        // not pointing to a Location marker (with marker._icon).
        function incrementMarkerId(id) {
          const [prefix, suffix] = id.split('-');
          return `${prefix}-${Number(suffix) + 1}`;
        }

        // Shared element hover logic, called both from mouseenter (when the
        // gate is already open) and from the dwell timeout (when the mouse was
        // already resting on an element as the gate opened).
        function handleElementHover(el) {
          currentMarkerId = el.dataset.markerId;
          // Until the currentMarkerId is not pointing to a Location
          // marker (with marker._icon) ...
          while (markers[currentMarkerId]?._icon === undefined) {
            // Increment by 1 the MarkerId identifier.
            currentMarkerId = incrementMarkerId(currentMarkerId);
          }
          const marker = markers[currentMarkerId];
          if (!marker || typeof marker.getLatLng !== 'function') {
            return;
          }
          const center = marker.getLatLng();
          el.parentElement.classList.add('marker-selected');

          // Move Y center map of -50 pixels
          /* const centerInPx = map.latLngToContainerPoint(center);
          const newCenterInPx = {
            ...centerInPx,
            y: centerInPx.y - 50,
          };
          const newCenterInCoords = map.containerPointToLatLng(newCenterInPx)
          //map.setView(newCenterInCoords, 16);

          map.flyTo(center, 16, {
            duration: 0.4,
            animate: true
          });*/

          map.setView(center, 16);
          // marker.closeTooltip(); // not needed if the marker.fire('click') is not used
          clickTimeout = setTimeout(() => {
            el.parentElement.classList.remove('marker-selected');
            marker.openPopup(center, {"autoPan":true, autoPanPadding: [0, 70]});
          }, 400);
        }

        const elements = once(
          'geoPlacesHover',
          root.querySelectorAll(
            //'.view-display-id-block_geoplaces_locations .views-field .marker-selector'
            '.leaflet-sidebar-list' + '.' + mapid + ' .list-item .marker-selector'
          )
        );

        elements.forEach((el) => {
          el.addEventListener('click', (e) => {
            handleElementHover(el);
            // On small screens, collapse the sidebar after triggering the
            // map marker interaction, so it doesn't obscure the map.
            // stopPropagation prevents the click from bubbling up to the
            // legendDiv handler, which would immediately re-open the sidebar.
            if (window.innerWidth <= 700) {
              const sidebarDiv = root.querySelector(leaflet_sidebar_list_mapid_selector);
              if (sidebarDiv) {
                sidebarDiv.classList.add('list-collapsed');
                e.stopPropagation();
              }
            }
          });
        });

        if (window.innerWidth <= 760) {
          const sidebarDiv = root.querySelector(leaflet_sidebar_list_mapid_selector);
          if (sidebarDiv) {
            sidebarDiv.classList.add('list-collapsed');
          }
        }

      }
    }

  };

  /**
   * Leaflet interactions behavior.
   */
  Drupal.behaviors.leafletInteractions = {

    // Default zoom level for icon sizing.
    zoomDefaultIconSize: 18,

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
        const root = context || document;

        Drupal.Leaflet[mapid].imagesZoomLimit = 16;

        // Trigger/Process Initial Actions.
        self.processInitialActions(mapid, map, features, markers, markersOriginalSizes);

        // Set Actions on every Zoom End.
        map.on('zoomend', function () {
          // Markers resize on Zoomend.
          self.markersResizeOnZoomEnd(mapid, map, features, markers, markersOriginalSizes);

          // Set Tooltip Visibility, depending on Map Zoom.
          self.setPermanentTooltipVisibility(mapid, map);
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
            lFeature.arrowheads({size: '7%'});
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
              const pulsing_marker = new L.Marker(new L.LatLng(feature.lat, feature.lon), {
                icon: L.divIcon({
                  className: pulsing_type + ' pulsing-marker',
                  html: '<span class="pulsing-marker dot"></span>',
                }),
                iconSize: [100, 100]
              });

              // Add the pulsing marker to the map.
              if (pulsing_marker) {
                Drupal.Leaflet.prototype.feature_bind_popup(pulsing_marker, feature);

                // Add marker to appropriate layer group.
                if (layers_groups?.unclustered &&
                  feature.group_label &&
                  layers_groups.unclustered.hasOwnProperty(feature.group_label)) {
                  pulsing_marker.addTo(layers_groups.unclustered[feature.group_label]);
                }
                else if (layers_groups &&
                  feature.group_label &&
                  layers_groups.hasOwnProperty(feature.group_label)) {
                  pulsing_marker.addTo(layers_groups[feature.group_label]);
                }
                else if (add_features?.lMap) {
                  pulsing_marker.addTo(add_features.lMap);
                }
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
                }
                // Otherwise Remove the marker directly from the map.
                else {
                  map.removeLayer(markers[i]);
                }

                // Eventually remove the Marker from its Group Overlay cluster.
                const overlays = Drupal.Leaflet[mapid].overlays[markers[i].options.group_label];
                if (overlays && overlays._layers) {
                  for (const k in overlays._layers) {
                    if (Object.prototype.hasOwnProperty.call(overlays._layers, k) &&
                      overlays._layers[k] &&
                      overlays._layers[k]._markerCluster) {
                      overlays._layers[k].removeLayer(markers[i]);
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
                }
                // Otherwise add it directly to the map.
                else {
                  markers[i].addTo(map);
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
        if (overlays[value]) {
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
    }

  };

})(jQuery, Drupal, once, drupalSettings);
