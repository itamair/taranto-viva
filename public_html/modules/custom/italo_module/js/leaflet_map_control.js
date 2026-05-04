(function ($, Drupal) {

  'use strict';

  /**
   * Leaflet Images Control behavior.
   */
  Drupal.behaviors.leafletImagesControl = {

    /**
     * Attaches the behavior to the context.
     */
    attach: function(context) {
      const self = this;

      // Define a boolean leafletMapInit in the context, so not to perform same
      // bind actions more than ones.
      context.leafletMapInitImagesControl = false;

      // React on leafletMapInit event.
      // Resizing Markers.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        if (context.leafletMapInitImagesControl) {
          return;
        }
        context.leafletMapInitImagesControl = true;
        const map = lMap;
        const imagesZoomLimit = Drupal.Leaflet[mapid].imagesZoomLimit;

        // Get overlays from Drupal.Leaflet
        const overlays = Drupal.Leaflet[mapid].overlays;
        const overlayName = Drupal.t('Images')

        // Add the L.Control.ImagesToggle only if Overlays enabled (not empty).
        if (overlays && overlays[overlayName]) {
          // Add Images/Immagini toggle control
          L.Control.ImagesToggle = L.Control.extend({
            onAdd: function(map) {
              const container = L.DomUtil.create('div', 'leaflet-control-images-toggle leaflet-bar leaflet-control');
              const label = L.DomUtil.create('label', 'images-toggle-label', container);
              const checkbox = L.DomUtil.create('input', 'images-toggle-checkbox', label);
              const labelText = L.DomUtil.create('span', '', label);

              labelText.innerHTML = overlayName;
              checkbox.type = 'checkbox';

              // Function to update checkbox state based on overlay visibility
              const updateCheckboxState = function() {
                // Check if overlay exists and is on the map
                if (overlays[overlayName]) {
                  // Check if overlay is currently on the map
                  checkbox.checked = map.hasLayer(overlays[overlayName]);
                } else {
                  checkbox.checked = false;
                }
              };

              // Initialize checkbox state based on current overlay visibility
              updateCheckboxState();

              // Style the control
              container.style.backgroundColor = 'white';
              container.style.padding = '5px 8px';
              container.style.borderRadius = '4px';
              container.style.cursor = 'pointer';
              container.style.boxShadow = '0 1px 5px rgba(0,0,0,0.4)';

              label.style.display = 'flex';
              label.style.alignItems = 'center';
              label.style.gap = '5px';
              label.style.margin = '0';
              label.style.cursor = 'pointer';

              checkbox.style.margin = '0';
              checkbox.style.cursor = 'pointer';

              labelText.style.fontWeight = '500';

              // Prevent map click events
              L.DomEvent.disableClickPropagation(container);

              // Toggle overlay when checkbox changes
              L.DomEvent.on(checkbox, 'change', function(e) {
                // Prevent handling the change event recursively
                L.DomEvent.stopPropagation(e);

                if (checkbox.checked) {
                  // Only add if zoom level permits and overlay exists
                  if (map.getZoom() >= imagesZoomLimit && overlays[overlayName] && !map.hasLayer(overlays[overlayName])) {
                    map.addLayer(overlays[overlayName]);
                  }
                  sessionStorage.setItem('imagesOverlayActive', '1');
                } else {
                  // Remove overlay if it exists and is on the map
                  if (overlays[overlayName] && map.hasLayer(overlays[overlayName])) {
                    map.removeLayer(overlays[overlayName]);
                  }
                  sessionStorage.setItem('imagesOverlayActive', '0');
                }
              });

              // Update checkbox when overlay is added/removed by other means
              map.on('overlayadd', function(event) {
                if (event.name === 'Images' || event.name === 'Immagini') {
                  checkbox.checked = true;
                }
              });

              map.on('overlayremove', function(event) {
                if (event.name === 'Images' || event.name === 'Immagini') {
                  checkbox.checked = false;
                }
              });

              return container;
            }
          });

          L.control.imagesToggle = function(opts) {
            return new L.Control.ImagesToggle(opts);
          }

          // Create the ImagesToggle control but don't add it to the map yet
          const imagesToggleControl = L.control.imagesToggle({ position: 'topright' });
          let controlAdded = false;

          // Function to add or remove the control based on zoom level
          const updateImagesToggleControl = function() {
            if (map.getZoom() >= imagesZoomLimit) {
              if (!controlAdded) {
                // Add the control to the map
                imagesToggleControl.addTo(map);

                // Move our control to be the first one in the top-right
                const controlContainer = map._controlCorners.topright;
                const ourControl = controlContainer.lastChild;
                controlContainer.insertBefore(ourControl, controlContainer.firstChild);

                controlAdded = true;
              }
            } else if (controlAdded) {
              // Remove the control when zoom is below the limit
              imagesToggleControl.remove();
              controlAdded = false;
            }
          };

          // Initial setup based on current zoom
          updateImagesToggleControl();

          // Update control visibility when zoom changes
          map.on('zoomend', updateImagesToggleControl);
        }
      });
    }
  };

  /**
   * Leaflet PM Places Control behavior.
   */
  Drupal.behaviors.leafletPmPlacesControl = {

    /**
     * Attaches the behavior to the context.
     */
    attach: function(context) {

      const self = this;

      // Define a boolean leafletMapInit in the context, so not to perform same
      // bind actions more than ones.
      context.leafletMapInitPmPlacesControl = false;

      // React on leafletMapInit event.
      // Resizing Markers.
      $(context).on('leafletMapInit', function (e, settings, lMap, mapid, data_markers) {
        if (context.leafletMapInitPmPlacesControl) {
          return;
        }

        context.leafletMapInitPmPlacesControl = true;
        const map = lMap;
        const placesZoomLimit = Drupal.Leaflet[mapid].placesZoomLimit;

        // Get overlays from Drupal.Leaflet
        const overlays = Drupal.Leaflet[mapid].overlays;
        const overlayName = Drupal.Leaflet[mapid].placesOverlayName;

        // Add the L.Control.ImagesToggle only if Overlays enabled (not empty).
        if (overlays && overlays[overlayName]) {
          // Add Images/Immagini toggle control
          L.Control.PlacesToggle = L.Control.extend({
            onAdd: function(map) {
              const container = L.DomUtil.create('div', 'leaflet-control-places-toggle leaflet-bar leaflet-control');
              const label = L.DomUtil.create('label', 'places-toggle-label', container);
              const checkbox = L.DomUtil.create('input', 'places-toggle-checkbox', label);
              const labelText = L.DomUtil.create('span', '', label);

              labelText.innerHTML = overlayName;
              checkbox.type = 'checkbox';

              // Function to update checkbox state based on overlay visibility
              const updateCheckboxState = function() {
                // Check if overlay exists and is on the map
                if (overlays[overlayName]) {
                  // Check if overlay is currently on the map
                  checkbox.checked = map.hasLayer(overlays[overlayName]);
                } else {
                  checkbox.checked = false;
                }
              };

              // Initialize checkbox state based on current overlay visibility
              updateCheckboxState();

              // Style the control
              container.style.backgroundColor = 'white';
              container.style.padding = '5px 8px';
              container.style.borderRadius = '4px';
              container.style.cursor = 'pointer';
              container.style.boxShadow = '0 1px 5px rgba(0,0,0,0.4)';

              label.style.display = 'flex';
              label.style.alignItems = 'center';
              label.style.gap = '5px';
              label.style.margin = '0';
              label.style.cursor = 'pointer';

              checkbox.style.margin = '0';
              checkbox.style.cursor = 'pointer';

              labelText.style.fontWeight = '500';

              // Prevent map click events
              L.DomEvent.disableClickPropagation(container);

              // Toggle overlay when checkbox changes
              L.DomEvent.on(checkbox, 'change', function(e) {
                // Prevent handling the change event recursively
                L.DomEvent.stopPropagation(e);

                if (checkbox.checked) {
                  // Only add if zoom level permits and overlay exists
                  if (map.getZoom() >= placesZoomLimit && overlays[overlayName] && !map.hasLayer(overlays[overlayName])) {
                    map.addLayer(overlays[overlayName]);
                  }
                  sessionStorage.setItem('placesOverlayActive', '1');
                } else {
                  // Remove overlay if it exists and is on the map
                  if (overlays[overlayName] && map.hasLayer(overlays[overlayName])) {
                    map.removeLayer(overlays[overlayName]);
                  }
                  sessionStorage.setItem('placesOverlayActive', '0');
                }
              });

              // Update checkbox when overlay is added/removed by other means
              map.on('overlayadd', function(event) {
                if (event.name === Drupal.Leaflet[mapid].placesOverlayName) {
                  checkbox.checked = true;
                }
              });

              map.on('overlayremove', function(event) {
                if (event.name === Drupal.Leaflet[mapid].placesOverlayName) {
                  checkbox.checked = false;
                }
              });

              return container;
            }
          });

          L.control.placesToggle = function(opts) {
            return new L.Control.PlacesToggle(opts);
          }

          // Create the PlacesToggle control but don't add it to the map yet
          const placesToggleControl = L.control.placesToggle({ position: 'topright' });
          let controlAdded = false;

          // Function to add or remove the control based on zoom level
          const updatePlacesToggleControl = function() {
            if (map.getZoom() >= placesZoomLimit) {
              if (!controlAdded) {
                // Add the control to the map
                placesToggleControl.addTo(map);

                // Move our control to be the first one in the top-right
                const controlContainer = map._controlCorners.topright;
                const ourControl = controlContainer.lastChild;
                controlContainer.insertBefore(ourControl, controlContainer.firstChild);

                controlAdded = true;
              }
            } else if (controlAdded) {
              // Remove the control when zoom is below the limit
              placesToggleControl.remove();
              controlAdded = false;
            }
          };

          // Initial setup based on current zoom
          updatePlacesToggleControl();

          // Update control visibility when zoom changes
          map.on('zoomend', updatePlacesToggleControl);
        }
      });
    }
  };

  /**
   * Leaflet Sidebar List Control behavior.
   */
  Drupal.behaviors.leafletSidebarListControl = {
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
      const sidebarListControl = L.control({ position: 'topleft' });

      sidebarListControl.onAdd = function (map) {
        const div = L.DomUtil.create('div', 'leaflet-control leaflet-sidebar-list' + ' ' + classes);

        // Create List Collapsed title.
        const listCollapsedTitle = L.DomUtil.create('div', 'list-collapsed-title', div);
        // Apply custom List Collapsed Label content.
        listCollapsedTitle.innerHTML = settings.list.title;

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

        // Add search input.
        const searchWrapper = L.DomUtil.create('div', 'list-search-wrapper', content);
        // const searchLabel = L.DomUtil.create('label', 'list-search-label', searchWrapper);
        // searchLabel.textContent = Drupal.t('Search');
        const searchInput = L.DomUtil.create('input', 'list-search-input', searchWrapper);
        searchInput.type = 'text';
        searchInput.placeholder = Drupal.t('Search...');
        searchInput.setAttribute('autocomplete', 'off');

        // Add List Items.
        const list_items = settings.list.items;

        const items = L.DomUtil.create('div', 'list-items', content);

        for (let i = 0; i < list_items.length; i++) {
          const item = L.DomUtil.create('div', 'list-item', items);
          item.innerHTML = list_items[i];
        }

        content.appendChild(items);

        // Filter list items on search input (minimum 3 characters).
        searchInput.addEventListener('input', function () {
          const query = searchInput.value.trim().toLowerCase();
          const listItemEls = items.querySelectorAll('.list-item');
          listItemEls.forEach(function (item) {
            if (query.length < 2) {
              item.style.display = '';
            }
            else {
              item.style.display = item.textContent.toLowerCase().includes(query) ? '' : 'none';
            }
          });
        });

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
      const source_endpoint = '/' + pageLang + '/taranto-viva-geoplaces-list';
      fetch(source_endpoint)
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
   * Always Reorder Leaflet Control Layers to first position in top-right corner.
   */
  Drupal.behaviors.leafletControlLayersReorder = {

    /**
     * Attaches the behavior to the context.
     */
    attach: function(context) {
      context.leafletMapInitControlLayersReorder = false;

      $(context).on('leafletMapInit', function (e, settings, lMap, mapid) {
        // Apply this altering to all Leaflet Maps.
        /* if (mapid !== 'leaflet-map-view-geo-places-page-map-taranto-viva') {
            return;
           }*/
        if (context.leafletMapInitControlLayersReorder) {
          return;
        }
        context.leafletMapInitControlLayersReorder = true;

        const controlContainer = lMap._controlCorners.topright;
        const layersControl = controlContainer.querySelector('.leaflet-control-layers');
        if (layersControl && layersControl !== controlContainer.firstChild) {
          controlContainer.insertBefore(layersControl, controlContainer.firstChild);
        }
      });
    }
  };

})(jQuery, Drupal);
