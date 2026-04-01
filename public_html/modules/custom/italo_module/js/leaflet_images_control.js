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

})(jQuery, Drupal);
